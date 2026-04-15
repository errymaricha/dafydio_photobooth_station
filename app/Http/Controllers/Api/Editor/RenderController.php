<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenderEditJobRequest;
use App\Models\AssetFile;
use App\Models\EditJob;
use App\Models\RenderedOutput;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Interfaces\ImageInterface;

class RenderController extends Controller
{
    public function store(RenderEditJobRequest $request, EditJob $editJob)
    {
        $existingRendered = RenderedOutput::where('edit_job_id', $editJob->id)
            ->where('is_active', true)
            ->first();

        if ($existingRendered && !$request->boolean('force')) {
            $existingRendered->load('file');

            return response()->json([
                'message' => 'Rendered output already exists',
                'rendered_output_id' => $existingRendered->id,
                'file_path' => $existingRendered->file?->file_path,
                'file_url' => $existingRendered->file
                    ? url('storage/' . $existingRendered->file->file_path)
                    : null,
                'status' => 'ready_print',
            ], 200);
        }

        $editJob->load([
            'session.station',
            'session.device',
            'template.slots',
            'items.sessionPhoto.originalFile',
        ]);

        if (!$editJob->session || !$editJob->template || !$editJob->session->station) {
            return response()->json([
                'message' => 'Edit job data incomplete.'
            ], 422);
        }

        $canvasWidth = $editJob->template->canvas_width ?? 1200;
        $canvasHeight = $editJob->template->canvas_height ?? 1800;

        $manager = new ImageManager(new Driver());

        $backgroundColor = $editJob->template->config_json['background_color'] ?? '#ffffff';
        $canvas = $manager->create($canvasWidth, $canvasHeight)->fill($backgroundColor);

        $slots = $editJob->template->slots->keyBy('slot_index');
        $placedItems = 0;

        foreach ($editJob->items as $item) {
            $slot = $slots->get($item->slot_index);
            $sessionPhoto = $item->sessionPhoto;
            $originalFile = $sessionPhoto?->originalFile;

            if (!$slot || !$originalFile) {
                continue;
            }

            if (!Storage::disk($originalFile->storage_disk)->exists($originalFile->file_path)) {
                continue;
            }

            $binary = Storage::disk($originalFile->storage_disk)->get($originalFile->file_path);
            $image = $manager->read($binary);

            $targetWidth = $slot->width;
            $targetHeight = $slot->height;

            $this->applyTransformAdjustment($image, $item->transform_json ?? null);
            $this->applyCropAdjustment(
                $image,
                $item->crop_json ?? null,
                $targetWidth,
                $targetHeight,
            );

            $canvas->place($image, 'top-left', $slot->x, $slot->y);
            $placedItems++;
        }

        if ($placedItems === 0) {
            return response()->json([
                'message' => 'No renderable items found for this edit job.',
            ], 422);
        }

        $this->applyDynamicLayers(
            $canvas,
            $editJob->template->config_json['dynamic_layers'] ?? [],
            $manager,
            $editJob,
        );

        $datePath = now()->format('Y/m/d');
        $renderDir = 'stations/'
            . $editJob->session->station->station_code
            . '/sessions/'
            . $datePath
            . '/'
            . $editJob->session->session_code
            . '/rendered';

        $versionNo = $editJob->version_no;
        $fileName = 'FINAL_V' . $versionNo . '.png';
        $renderPath = $renderDir . '/' . $fileName;

        Storage::disk('public')->put(
            $renderPath,
            $canvas->encode(new PngEncoder())->toString()
        );

        try {
            $renderedOutput = DB::transaction(function () use ($canvasHeight, $canvasWidth, $editJob, $fileName, $renderPath, $versionNo) {
                $asset = AssetFile::create([
                    'id' => (string) Str::uuid(),
                    'storage_disk' => 'public',
                    'file_path' => $renderPath,
                    'file_name' => $fileName,
                    'file_ext' => 'png',
                    'mime_type' => 'image/png',
                    'file_size_bytes' => Storage::disk('public')->size($renderPath),
                    'width' => $canvasWidth,
                    'height' => $canvasHeight,
                    'file_category' => 'rendered',
                    'created_by_type' => 'system',
                    'created_by_id' => null,
                ]);

                RenderedOutput::where('session_id', $editJob->session_id)
                    ->update(['is_active' => false]);

                $renderedOutput = RenderedOutput::create([
                    'id' => (string) Str::uuid(),
                    'session_id' => $editJob->session_id,
                    'edit_job_id' => $editJob->id,
                    'file_id' => $asset->id,
                    'version_no' => $versionNo,
                    'render_type' => 'final_print',
                    'width' => $canvasWidth,
                    'height' => $canvasHeight,
                    'dpi' => 300,
                    'is_active' => true,
                    'rendered_at' => now(),
                ]);

                $editJob->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                if (in_array($editJob->session->status, ['uploaded', 'editing'], true)) {
                    $editJob->session->update([
                        'status' => 'ready_print',
                    ]);
                }

                return $renderedOutput;
            });
        } catch (\Throwable $throwable) {
            Storage::disk('public')->delete($renderPath);

            throw $throwable;
        }

        return response()->json([
            'message' => 'Rendered output created',
            'rendered_output_id' => $renderedOutput->id,
            'file_path' => $renderPath,
            'file_url' => url('storage/' . $renderPath),
            'status' => 'ready_print',
        ], 201);
    }

    /**
     * Apply non-destructive transform controls captured from the editor UI
     * before the image is cropped into the target slot.
     *
     * @param  array<string, mixed>|null  $transform
     */
    protected function applyTransformAdjustment(ImageInterface $image, ?array $transform): void
    {
        $rotation = (float) ($transform['rotation'] ?? 0);

        if ($rotation === 0.0) {
            return;
        }

        $image->rotate($rotation);
    }

    /**
     * Apply crop semantics from the editor UI.
     *
     * Two payload styles are supported:
     * - Legacy explicit crop rectangle: x, y, width, height
     * - New slot-based framing payload: zoom, offset_x, offset_y
     *
     * The new payload behaves like CSS `object-cover` plus zoom and pan,
     * which keeps the browser preview and backend render aligned.
     *
     * @param  array<string, mixed>|null  $crop
     */
    protected function applyCropAdjustment(
        ImageInterface $image,
        ?array $crop,
        int $targetWidth,
        int $targetHeight,
    ): void {
        if (!$crop) {
            $image->cover($targetWidth, $targetHeight);

            return;
        }

        if (
            array_key_exists('x', $crop) ||
            array_key_exists('y', $crop) ||
            array_key_exists('width', $crop) ||
            array_key_exists('height', $crop)
        ) {
            $cropX = max(0, (int) ($crop['x'] ?? 0));
            $cropY = max(0, (int) ($crop['y'] ?? 0));
            $cropWidth = max(1, (int) ($crop['width'] ?? $image->width()));
            $cropHeight = max(1, (int) ($crop['height'] ?? $image->height()));

            $image->crop($cropWidth, $cropHeight, $cropX, $cropY);
            $image->cover($targetWidth, $targetHeight);

            return;
        }

        $zoom = max(1.0, (float) ($crop['zoom'] ?? 1));
        $offsetX = $this->clamp((float) ($crop['offset_x'] ?? 0), -100, 100);
        $offsetY = $this->clamp((float) ($crop['offset_y'] ?? 0), -100, 100);

        $imageWidth = max(1, $image->width());
        $imageHeight = max(1, $image->height());
        $slotAspectRatio = $targetWidth / max($targetHeight, 1);
        $imageAspectRatio = $imageWidth / max($imageHeight, 1);

        if ($imageAspectRatio > $slotAspectRatio) {
            $cropHeight = $imageHeight;
            $cropWidth = (int) round($imageHeight * $slotAspectRatio);
        } else {
            $cropWidth = $imageWidth;
            $cropHeight = (int) round($imageWidth / $slotAspectRatio);
        }

        $cropWidth = max(1, min($imageWidth, (int) round($cropWidth / $zoom)));
        $cropHeight = max(1, min($imageHeight, (int) round($cropHeight / $zoom)));

        $maxShiftX = max(0.0, ($imageWidth - $cropWidth) / 2);
        $maxShiftY = max(0.0, ($imageHeight - $cropHeight) / 2);

        $centerX = ($imageWidth / 2) + ($maxShiftX * ($offsetX / 100));
        $centerY = ($imageHeight / 2) + ($maxShiftY * ($offsetY / 100));

        $cropX = (int) round(
            $this->clamp($centerX - ($cropWidth / 2), 0, $imageWidth - $cropWidth)
        );
        $cropY = (int) round(
            $this->clamp($centerY - ($cropHeight / 2), 0, $imageHeight - $cropHeight)
        );

        $image->crop($cropWidth, $cropHeight, $cropX, $cropY);
        $image->resize($targetWidth, $targetHeight);
    }

    /**
     * @param  array<int, array<string, mixed>>  $layers
     */
    protected function applyDynamicLayers(
        ImageInterface $canvas,
        array $layers,
        ImageManager $manager,
        EditJob $editJob,
    ): void {
        foreach ($layers as $layer) {
            $type = (string) ($layer['type'] ?? '');
            $x = (int) ($layer['x'] ?? 0);
            $y = (int) ($layer['y'] ?? 0);
            $opacity = (int) ($layer['opacity'] ?? 100);
            $enabled = (bool) ($layer['enabled'] ?? true);

            if (!$enabled) {
                continue;
            }

            if ($type === 'text') {
                $text = (string) ($layer['text'] ?? $layer['label'] ?? '');
                $text = $this->resolveLayerVariables($text, $editJob);

                if ($text === '') {
                    continue;
                }

                $fontSize = (int) ($layer['font_size'] ?? 36);
                $align = (string) ($layer['align'] ?? 'left');
                $color = $this->resolveLayerColor(
                    (string) ($layer['color'] ?? '#111827'),
                    $opacity,
                );

                $canvas->text($text, $x, $y, function ($font) use ($fontSize, $align, $color): void {
                    $font->size($fontSize);
                    $font->color($color);
                    $font->align($align);
                    $font->valign('top');
                });

                continue;
            }

            if ($type === 'qr') {
                $data = (string) ($layer['qr_data'] ?? '');
                $data = $this->resolveLayerVariables($data, $editJob);

                if ($data === '') {
                    continue;
                }

                $size = (int) ($layer['width'] ?? $layer['height'] ?? 160);
                $size = max(60, $size);
                $padding = (int) ($layer['padding'] ?? 0);
                $padding = max(0, min($padding, (int) floor($size / 2)));
                $qrSize = max(60, $size - ($padding * 2));
                $bgColor = $this->resolveLayerColorValue(
                    (string) ($layer['bg_color'] ?? '#ffffff')
                );

                $qrCode = new QrCode($data);
                $qrCode->setSize($qrSize);
                $qrCode->setMargin(0);
                $qrCode->setForegroundColor(new Color(0, 0, 0));
                $qrCode->setBackgroundColor(new Color(
                    $bgColor['r'],
                    $bgColor['g'],
                    $bgColor['b'],
                ));

                $writer = new PngWriter();
                $pngData = $writer->write($qrCode)->getString();
                $qrCanvas = $manager->read($pngData);

                if ($padding > 0) {
                    $wrapper = $manager->create($size, $size)->fill($bgColor['hex']);
                    $wrapper->place($qrCanvas, 'top-left', $padding, $padding);
                    $qrCanvas = $wrapper;
                }

                $qrCanvas->opacity(max(0, min(100, $opacity)));

                $canvas->place($qrCanvas, 'top-left', $x, $y);
            }
        }
    }

    protected function resolveLayerColor(string $color, int $opacity): string
    {
        $opacity = max(0, min(100, $opacity));

        if (!str_starts_with($color, '#')) {
            return $color;
        }

        $hex = ltrim($color, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return $color;
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $alpha = round($opacity / 100, 2);

        return "rgba({$red}, {$green}, {$blue}, {$alpha})";
    }

    /**
     * @return array{r:int,g:int,b:int,hex:string}
     */
    protected function resolveLayerColorValue(string $color): array
    {
        $hex = ltrim($color, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return [
                'r' => 255,
                'g' => 255,
                'b' => 255,
                'hex' => '#ffffff',
            ];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
            'hex' => '#' . $hex,
        ];
    }

    protected function resolveLayerVariables(string $value, EditJob $editJob): string
    {
        $session = $editJob->session;
        $station = $session?->station;

        $replacements = [
            '{session_code}' => $session?->session_code ?? '',
            '{station_code}' => $station?->station_code ?? '',
            '{station_name}' => $station?->station_name ?? '',
            '{device_name}' => $session?->device?->device_name ?? '',
            '{render_date}' => now()->format('Y-m-d'),
            '{render_time}' => now()->format('H:i'),
        ];

        return strtr($value, $replacements);
    }

    protected function clamp(float $value, float $minimum, float $maximum): float
    {
        return max($minimum, min($maximum, $value));
    }
}
