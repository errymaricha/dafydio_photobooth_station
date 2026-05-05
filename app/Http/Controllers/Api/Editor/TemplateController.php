<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateOverlayRequest;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\StoreTemplateSlotRequest;
use App\Http\Requests\StoreTemplateThumbnailRequest;
use App\Http\Requests\TemplateQrPreviewRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Http\Requests\UpdateTemplateSlotsRequest;
use App\Models\AssetFile;
use App\Models\Template;
use App\Models\TemplateAsset;
use App\Models\TemplateSlot;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;

class TemplateController extends Controller
{
    public function index()
    {
        $status = request()->string('status')->lower()->value();
        $query = Template::with(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        } else {
            $query->where('status', 'active');
        }

        $templates = $query
            ->get()
            ->map(fn (Template $template): array => $this->transformTemplate($template))
            ->values();

        return response()->json($templates);
    }

    public function store(StoreTemplateRequest $request)
    {
        $validated = $request->validated();
        $editorId = $request->user()?->id;

        $template = DB::transaction(function () use ($editorId, $validated): Template {
            $template = Template::create([
                'id' => (string) Str::uuid(),
                'template_code' => $validated['template_code'] ?? $this->generateTemplateCode(),
                'template_name' => $validated['template_name'],
                'category' => $validated['category'] ?? null,
                'paper_size' => $validated['paper_size'] ?? null,
                'canvas_width' => $validated['canvas_width'],
                'canvas_height' => $validated['canvas_height'],
                'preview_url' => $validated['preview_url'] ?? null,
                'config_json' => $validated['config_json'] ?? null,
                'status' => 'active',
                'created_by' => $editorId,
                'updated_by' => $editorId,
            ]);

            $this->createDefaultSlot($template, slotIndex: 1);

            return $template;
        });

        $template->load(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        return response()->json([
            'message' => 'Template created.',
            ...$this->transformTemplate($template),
        ], 201);
    }

    public function show(Template $template)
    {
        $template->load(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        return response()->json($this->transformTemplate($template));
    }

    public function update(UpdateTemplateRequest $request, Template $template)
    {
        $validated = $request->validated();

        $template->update([
            ...$validated,
            'updated_by' => $request->user()?->id,
        ]);

        $template->load(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        return response()->json([
            'message' => 'Template updated.',
            ...$this->transformTemplate($template),
        ]);
    }

    public function duplicate(Request $request, Template $template)
    {
        $template->load('slots');
        $editorId = $request->user()?->id;

        $duplicated = DB::transaction(function () use ($editorId, $template): Template {
            $copy = Template::create([
                'id' => (string) Str::uuid(),
                'template_code' => $this->generateTemplateCode(),
                'template_name' => $template->template_name.' Copy',
                'category' => $template->category,
                'paper_size' => $template->paper_size,
                'canvas_width' => $template->canvas_width,
                'canvas_height' => $template->canvas_height,
                'preview_url' => $template->preview_url,
                'config_json' => $template->config_json,
                'status' => $template->status,
                'created_by' => $editorId ?? $template->created_by,
                'updated_by' => $editorId ?? $template->updated_by,
            ]);

            $template->slots->each(function (TemplateSlot $slot) use ($copy): void {
                TemplateSlot::create([
                    'id' => (string) Str::uuid(),
                    'template_id' => $copy->id,
                    'slot_index' => $slot->slot_index,
                    'x' => $slot->x,
                    'y' => $slot->y,
                    'width' => $slot->width,
                    'height' => $slot->height,
                    'rotation' => $slot->rotation,
                    'border_radius' => $slot->border_radius,
                    'metadata_json' => $slot->metadata_json,
                ]);
            });

            return $copy;
        });

        $duplicated->load(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        return response()->json([
            'message' => 'Template duplicated.',
            ...$this->transformTemplate($duplicated),
        ], 201);
    }

    public function destroy(UpdateTemplateRequest $request, Template $template)
    {
        $reason = $request->string('reason')->value();
        $actorId = $request->user()?->id;

        DB::transaction(function () use ($actorId, $reason, $template): void {
            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'entity_type' => 'template',
                'entity_id' => $template->id,
                'action' => 'delete',
                'before_json' => json_encode($template->toArray()),
                'after_json' => json_encode(['reason' => $reason]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $template->delete();
        });

        return response()->json([
            'message' => 'Template deleted.',
        ]);
    }

    public function storeSlot(StoreTemplateSlotRequest $request, Template $template)
    {
        $validated = $request->validated();

        $slotIndex = (int) ($validated['slot_index'] ?? 0);
        $nextIndex = (int) $template->slots()->max('slot_index') + 1;
        $slotIndex = $slotIndex > 0 ? $slotIndex : $nextIndex;

        if ($template->slots()->where('slot_index', $slotIndex)->exists()) {
            return response()->json([
                'message' => 'Slot index sudah dipakai.',
            ], 422);
        }

        DB::transaction(function () use ($request, $template, $validated, $slotIndex): void {
            $slot = $this->createDefaultSlot($template, $slotIndex);

            $slot->update([
                'x' => (int) ($validated['x'] ?? $slot->x),
                'y' => (int) ($validated['y'] ?? $slot->y),
                'width' => (int) ($validated['width'] ?? $slot->width),
                'height' => (int) ($validated['height'] ?? $slot->height),
                'rotation' => (float) ($validated['rotation'] ?? $slot->rotation),
                'border_radius' => (int) ($validated['border_radius'] ?? $slot->border_radius),
            ]);

            if ($request->user()) {
                $template->update([
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        $template->load(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        return response()->json([
            'message' => 'Slot baru ditambahkan.',
            ...$this->transformTemplate($template),
        ], 201);
    }

    public function destroySlot(Request $request, Template $template, int $slotIndex)
    {
        $slot = $template->slots()->where('slot_index', $slotIndex)->first();

        if (! $slot) {
            return response()->json([
                'message' => 'Slot tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($request, $slot, $template): void {
            $slot->delete();

            if ($request->user()) {
                $template->update([
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        $template->load(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        return response()->json([
            'message' => 'Slot dihapus.',
            ...$this->transformTemplate($template),
        ]);
    }

    public function updateSlots(UpdateTemplateSlotsRequest $request, Template $template)
    {
        $validated = $request->validated();
        $slotPayload = collect($validated['slots'])->keyBy(
            fn (array $slot): int => (int) $slot['slot_index'],
        );

        DB::transaction(function () use ($request, $slotPayload, $template): void {
            $template->slots()
                ->whereIn('slot_index', $slotPayload->keys())
                ->get()
                ->each(function ($slot) use ($slotPayload): void {
                    $payload = $slotPayload->get((int) $slot->slot_index);

                    if (! $payload) {
                        return;
                    }

                    $slot->update([
                        'x' => (int) $payload['x'],
                        'y' => (int) $payload['y'],
                        'width' => (int) $payload['width'],
                        'height' => (int) $payload['height'],
                        'rotation' => (float) ($payload['rotation'] ?? 0),
                        'border_radius' => (int) ($payload['border_radius'] ?? 0),
                    ]);
                });

            if ($request->user()) {
                $template->update([
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        $template->load(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        return response()->json([
            'message' => 'Template slot layout updated.',
            ...$this->transformTemplate($template),
        ]);
    }

    public function uploadOverlay(StoreTemplateOverlayRequest $request, Template $template)
    {
        $file = $request->file('overlay');

        if (! $file) {
            return response()->json([
                'message' => 'Overlay file missing.',
            ], 422);
        }

        $editorId = $request->user()?->id;
        $template->load(['assets.file']);

        DB::transaction(function () use ($editorId, $file, $template): void {
            $existingAssets = $template->assets->filter(
                fn (TemplateAsset $asset) => $asset->asset_type === 'overlay_png',
            );

            foreach ($existingAssets as $existingAsset) {
                $fileModel = $existingAsset->file;

                if (
                    $fileModel
                    && Storage::disk($fileModel->storage_disk)->exists($fileModel->file_path)
                ) {
                    Storage::disk($fileModel->storage_disk)->delete($fileModel->file_path);
                }

                if ($fileModel) {
                    $fileModel->delete();
                }
            }

            $datePath = now()->format('Y/m/d');
            $dir = "templates/{$template->id}/overlay/{$datePath}";
            $fileName = 'overlay_'.substr((string) Str::uuid(), 0, 8).'.png';

            $manager = new ImageManager(new Driver);
            $image = $manager->read($file->getRealPath());

            $targetWidth = max(1, (int) $template->canvas_width);
            $targetHeight = max(1, (int) $template->canvas_height);
            $currentWidth = $image->width();
            $currentHeight = $image->height();

            if ($currentWidth !== $targetWidth || $currentHeight !== $targetHeight) {
                $image = $image->resize($targetWidth, $targetHeight);
            }

            $encodedOverlay = $image->encode(new PngEncoder)->toString();
            $this->assertAndroidSkiaSafePng($encodedOverlay);
            $storedPath = "{$dir}/{$fileName}";
            Storage::disk('public')->put($storedPath, $encodedOverlay);

            $assetFile = AssetFile::create([
                'id' => (string) Str::uuid(),
                'storage_disk' => 'public',
                'file_path' => $storedPath,
                'file_name' => $fileName,
                'file_ext' => 'png',
                'mime_type' => 'image/png',
                'file_size_bytes' => strlen($encodedOverlay),
                'file_category' => 'template_overlay',
                'created_by_type' => 'user',
                'created_by_id' => $editorId,
            ]);

            TemplateAsset::create([
                'id' => (string) Str::uuid(),
                'template_id' => $template->id,
                'asset_type' => 'overlay_png',
                'file_id' => $assetFile->id,
                'sort_order' => 0,
            ]);

            if ($editorId) {
                $template->update([
                    'updated_by' => $editorId,
                ]);
            }
        });

        $template->load(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        return response()->json([
            'message' => 'Overlay uploaded.',
            ...$this->transformTemplate($template),
        ], 201);
    }

    public function uploadThumbnail(StoreTemplateThumbnailRequest $request, Template $template)
    {
        $file = $request->file('thumbnail');

        if (! $file) {
            return response()->json([
                'message' => 'Thumbnail file missing.',
            ], 422);
        }

        $editorId = $request->user()?->id;
        $template->load(['assets.file']);

        DB::transaction(function () use ($editorId, $file, $template): void {
            $existingAssets = $template->assets->filter(
                fn (TemplateAsset $asset) => $asset->asset_type === 'thumbnail_image',
            );

            foreach ($existingAssets as $existingAsset) {
                $fileModel = $existingAsset->file;

                if (
                    $fileModel
                    && Storage::disk($fileModel->storage_disk)->exists($fileModel->file_path)
                ) {
                    Storage::disk($fileModel->storage_disk)->delete($fileModel->file_path);
                }

                if ($fileModel) {
                    $fileModel->delete();
                }
            }

            $datePath = now()->format('Y/m/d');
            $dir = "templates/{$template->id}/thumbnail/{$datePath}";
            $extension = $file->getClientOriginalExtension() ?: 'png';
            $extension = strtolower($extension);
            $fileName = 'thumbnail_'.substr((string) Str::uuid(), 0, 8).".{$extension}";

            $storedPath = $file->storeAs($dir, $fileName, 'public');

            $assetFile = AssetFile::create([
                'id' => (string) Str::uuid(),
                'storage_disk' => 'public',
                'file_path' => $storedPath,
                'file_name' => $fileName,
                'file_ext' => $extension,
                'mime_type' => $file->getMimeType(),
                'file_size_bytes' => $file->getSize(),
                'file_category' => 'template_thumbnail',
                'created_by_type' => 'user',
                'created_by_id' => $editorId,
            ]);

            TemplateAsset::create([
                'id' => (string) Str::uuid(),
                'template_id' => $template->id,
                'asset_type' => 'thumbnail_image',
                'file_id' => $assetFile->id,
                'sort_order' => 0,
            ]);

            if ($editorId) {
                $template->update([
                    'updated_by' => $editorId,
                ]);
            }
        });

        $template->load(['slots', 'createdBy', 'updatedBy', 'assets.file']);

        return response()->json([
            'message' => 'Thumbnail uploaded.',
            ...$this->transformTemplate($template),
        ], 201);
    }

    public function qrPreview(TemplateQrPreviewRequest $request)
    {
        $validated = $request->validated();
        $data = $validated['data'];
        $size = (int) ($validated['size'] ?? 160);
        $padding = (int) ($validated['padding'] ?? 0);
        $padding = max(0, min($padding, (int) floor($size / 2)));
        $qrSize = max(60, $size - ($padding * 2));
        $bgColor = $this->resolveLayerColorValue(
            (string) ($validated['bg_color'] ?? '#ffffff'),
        );

        $qrCode = new QrCode($data);
        $qrCode->setSize($qrSize);
        $qrCode->setMargin(0);
        $qrCode->setForegroundColor(new Color(0, 0, 0));
        $qrCode->setBackgroundColor(new Color($bgColor['r'], $bgColor['g'], $bgColor['b']));

        $writer = new PngWriter;
        $pngData = $writer->write($qrCode)->getString();

        if ($padding > 0) {
            $manager = new ImageManager(new Driver);
            $qrCanvas = $manager->read($pngData);
            $wrapper = $manager->create($size, $size)->fill($bgColor['hex']);
            $wrapper->place($qrCanvas, 'top-left', $padding, $padding);
            $pngData = $wrapper->encode(new PngEncoder)->toString();
        }

        return response()->json([
            'data_url' => 'data:image/png;base64,'.base64_encode($pngData),
        ]);
    }

    protected function transformTemplate(Template $template): array
    {
        $overlayAsset = $template->assets
            ->firstWhere('asset_type', 'overlay_png');
        $thumbnailAsset = $template->assets
            ->firstWhere('asset_type', 'thumbnail_image');

        $overlayUrl = null;
        $thumbnailUrl = null;

        if ($overlayAsset && $overlayAsset->file) {
            $overlayUrl = Storage::disk($overlayAsset->file->storage_disk)
                ->url($overlayAsset->file->file_path);
        }
        if ($thumbnailAsset && $thumbnailAsset->file) {
            $thumbnailUrl = Storage::disk($thumbnailAsset->file->storage_disk)
                ->url($thumbnailAsset->file->file_path);
        }

        return [
            'id' => $template->id,
            'template_code' => $template->template_code,
            'template_name' => $template->template_name,
            'category' => $template->category,
            'paper_size' => $template->paper_size,
            'canvas_width' => $template->canvas_width,
            'canvas_height' => $template->canvas_height,
            'thumbnail_url' => $thumbnailUrl,
            'preview_url' => $thumbnailUrl ?? $template->preview_url,
            'overlay_url' => $overlayUrl,
            'config' => $template->config_json,
            'status' => $template->status,
            'updated_at' => $template->updated_at,
            'created_by' => $template->createdBy ? [
                'id' => $template->createdBy->id,
                'name' => $template->createdBy->name,
            ] : null,
            'updated_by' => $template->updatedBy ? [
                'id' => $template->updatedBy->id,
                'name' => $template->updatedBy->name,
            ] : null,
            'slots' => $template->slots
                ->sortBy('slot_index')
                ->values()
                ->map(function ($slot) {
                    return [
                        'slot_index' => $slot->slot_index,
                        'x' => $slot->x,
                        'y' => $slot->y,
                        'width' => $slot->width,
                        'height' => $slot->height,
                        'rotation' => $slot->rotation,
                        'border_radius' => $slot->border_radius,
                        'metadata' => $slot->metadata_json,
                    ];
                })->values(),
        ];
    }

    /**
     * @return array{r:int,g:int,b:int,hex:string}
     */
    protected function resolveLayerColorValue(string $color): array
    {
        $hex = ltrim($color, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
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
            'hex' => '#'.$hex,
        ];
    }

    protected function generateTemplateCode(): string
    {
        return 'TPL-'.Str::upper(Str::random(6));
    }

    protected function createDefaultSlot(Template $template, int $slotIndex): TemplateSlot
    {
        $canvasWidth = (int) ($template->canvas_width ?? 1200);
        $canvasHeight = (int) ($template->canvas_height ?? 1800);
        $padding = 50;
        $slotWidth = max(100, $canvasWidth - ($padding * 2));
        $slotHeight = min(800, max(200, $canvasHeight - ($padding * 2)));
        $slotX = $padding;
        $slotY = min(
            $padding + (($slotIndex - 1) * ($slotHeight + 40)),
            max($padding, $canvasHeight - $slotHeight - $padding),
        );

        return TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'slot_index' => $slotIndex,
            'x' => $slotX,
            'y' => $slotY,
            'width' => $slotWidth,
            'height' => $slotHeight,
            'rotation' => 0,
            'border_radius' => 0,
        ]);
    }

    private function assertAndroidSkiaSafePng(string $pngBinary): void
    {
        if ($pngBinary === '') {
            throw ValidationException::withMessages([
                'overlay' => 'Overlay PNG kosong atau gagal di-encode.',
            ]);
        }

        $imageInfo = @getimagesizefromstring($pngBinary);
        if (! is_array($imageInfo) || ($imageInfo['mime'] ?? null) !== 'image/png') {
            throw ValidationException::withMessages([
                'overlay' => 'Overlay bukan PNG valid setelah diproses.',
            ]);
        }

        if (function_exists('imagecreatefromstring')) {
            $decoded = @imagecreatefromstring($pngBinary);

            if ($decoded === false) {
                throw ValidationException::withMessages([
                    'overlay' => 'Overlay tidak bisa didecode oleh engine PNG standar.',
                ]);
            }

            imagedestroy($decoded);
        }
    }
}
