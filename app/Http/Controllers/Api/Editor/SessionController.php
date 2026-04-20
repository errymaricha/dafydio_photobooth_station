<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaceFitRequest;
use App\Models\AssetFile;
use App\Models\PhotoSession;
use App\Models\PrintOrder;
use App\Models\RenderedOutput;
use App\Models\SessionPhoto;
use CV\CascadeClassifier;
use CV\Rect;
use CV\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $sessions = PhotoSession::with([
            'station',
            'device',
            'photos.thumbnailFile',
        ])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($session) {
                $thumbnailFile = $session->photos->first()?->thumbnailFile;

                return [
                    'id' => $session->id,
                    'session_code' => $session->session_code,
                    'name' => $session->session_code,
                    'status' => $session->status,
                    'captured_count' => $session->captured_count,
                    'created_at' => $session->created_at,
                    'captured_at' => $session->captured_at,
                    'completed_at' => $session->completed_at,
                    'device_name' => $session->device?->device_name,
                    'device_code' => $session->device?->device_code,
                    'station_code' => $session->station?->station_code,

                    'station' => [
                        'id' => $session->station?->id,
                        'code' => $session->station?->station_code,
                    ],

                    'device' => [
                        'id' => $session->device?->id,
                        'code' => $session->device?->device_code,
                    ],

                    'thumbnail' => $thumbnailFile?->file_path,
                    'thumbnail_url' => $this->assetUrl($thumbnailFile),
                ];
            })->values();

        return response()->json($sessions);
    }

    public function show(PhotoSession $session)
    {
        $session->load([
            'station',
            'device',
            'manualPaymentReviewer',
            'photos.originalFile',
            'photos.thumbnailFile',
            'editJobs.template',
            'vouchers',
        ]);

        $latestEditJob = $session->editJobs
            ->sortByDesc('version_no')
            ->first();

        $activeRenderedOutput = RenderedOutput::with('file')
            ->where('session_id', $session->id)
            ->where('is_active', true)
            ->latest('version_no')
            ->first();

        $latestPrintOrder = PrintOrder::with('printer')
            ->where('session_id', $session->id)
            ->latest('ordered_at')
            ->latest()
            ->first();

        return response()->json([
            'id' => $session->id,
            'session_code' => $session->session_code,
            'device_name' => $session->device?->device_name,
            'device_code' => $session->device?->device_code,
            'station_code' => $session->station?->station_code,
            'status' => $session->status,
            'captured_count' => $session->captured_count,
            'payment_status' => $session->payment_status,
            'payment_method' => $session->payment_method,
            'payment_ref' => $session->payment_ref,
            'paid_at' => $session->paid_at,
            'customer_whatsapp' => $session->customer_whatsapp,
            'additional_print_count' => $session->additional_print_count,
            'manual_payment_status' => $session->manual_payment_status,
            'manual_payment_reviewed_at' => $session->manual_payment_reviewed_at,
            'manual_payment_notes' => $session->manual_payment_notes,
            'manual_payment_reviewer_name' => $session->manualPaymentReviewer?->name,
            'created_at' => $session->created_at,
            'captured_at' => $session->captured_at,
            'completed_at' => $session->completed_at,

            'station' => [
                'id' => $session->station?->id,
                'code' => $session->station?->station_code,
            ],

            'device' => [
                'id' => $session->device?->id,
                'code' => $session->device?->device_code,
            ],

            'photos' => $session->photos->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'capture_index' => $photo->capture_index,
                    'is_selected' => $photo->is_selected,
                    'width' => $photo->width,
                    'height' => $photo->height,
                    'original_path' => $photo->originalFile?->file_path,
                    'thumbnail_path' => $photo->thumbnailFile?->file_path,
                    'original_url' => $this->assetUrl($photo->originalFile),
                    'thumbnail_url' => $this->assetUrl($photo->thumbnailFile),
                    'url' => $this->assetUrl($photo->thumbnailFile) ?? $this->assetUrl($photo->originalFile),
                ];
            })->values(),

            'latest_edit_job' => $latestEditJob ? [
                'id' => $latestEditJob->id,
                'version_no' => $latestEditJob->version_no,
                'status' => $latestEditJob->status,
                'completed_at' => $latestEditJob->completed_at,
                'template' => [
                    'id' => $latestEditJob->template?->id,
                    'template_name' => $latestEditJob->template?->template_name,
                ],
            ] : null,

            'active_rendered_output' => $activeRenderedOutput ? [
                'id' => $activeRenderedOutput->id,
                'version_no' => $activeRenderedOutput->version_no,
                'rendered_at' => $activeRenderedOutput->rendered_at,
                'file_url' => $this->assetUrl($activeRenderedOutput->file),
            ] : null,

            'latest_print_order' => $latestPrintOrder ? [
                'id' => $latestPrintOrder->id,
                'order_code' => $latestPrintOrder->order_code,
                'status' => $latestPrintOrder->status,
                'total_qty' => $latestPrintOrder->total_qty,
                'total_amount' => $latestPrintOrder->total_amount,
                'ordered_at' => $latestPrintOrder->ordered_at,
                'printer' => [
                    'id' => $latestPrintOrder->printer?->id,
                    'name' => $latestPrintOrder->printer?->printer_name,
                ],
            ] : null,

            'vouchers' => $session->vouchers
                ->sortByDesc('applied_at')
                ->values()
                ->map(function ($voucher) {
                    return [
                        'id' => $voucher->id,
                        'voucher_code' => $voucher->voucher_code,
                        'voucher_type' => $voucher->voucher_type,
                        'status' => $voucher->status,
                        'notes' => $voucher->notes,
                        'applied_at' => $voucher->applied_at,
                        'revoked_at' => $voucher->revoked_at,
                        'applied_by' => $voucher->applied_by,
                        'revoked_by' => $voucher->revoked_by,
                    ];
                }),
        ]);
    }

    public function faceFit(FaceFitRequest $request, PhotoSession $session, SessionPhoto $photo)
    {
        if ($photo->session_id !== $session->id) {
            return response()->json([
                'message' => 'Photo does not belong to this session.',
            ], 404);
        }

        $photo->load('originalFile');
        $file = $photo->originalFile;

        if (! $file) {
            return response()->json([
                'found' => false,
            ]);
        }

        $disk = Storage::disk($file->storage_disk);

        if (! $disk->exists($file->file_path)) {
            return response()->json([
                'found' => false,
            ]);
        }

        $path = $disk->path($file->file_path);
        $slotWidth = (int) $request->integer('slot_width');
        $slotHeight = (int) $request->integer('slot_height');

        if (app()->environment('testing')) {
            return response()->json([
                'found' => false,
            ]);
        }

        if (! extension_loaded('opencv')) {
            return response()->json([
                'found' => false,
            ]);
        }

        $cascadePath = '/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml';

        if (! file_exists($cascadePath)) {
            return response()->json([
                'found' => false,
            ]);
        }

        try {
            $src = \CV\imread($path);
        } catch (\Throwable $throwable) {
            return response()->json([
                'found' => false,
            ]);
        }

        if (! $src) {
            return response()->json([
                'found' => false,
            ]);
        }

        $gray = null;
        \CV\cvtColor($src, $gray, \CV\COLOR_BGR2GRAY);

        $classifier = new CascadeClassifier;

        if (! $classifier->load($cascadePath)) {
            return response()->json([
                'found' => false,
            ]);
        }

        $faces = null;
        $classifier->detectMultiScale($gray, $faces, 1.1, 3, 0, new Size(40, 40));
        $face = $this->pickLargestFace($faces);

        if (! $face) {
            return response()->json([
                'found' => false,
            ]);
        }

        $photoWidth = (int) ($photo->width ?? 0);
        $photoHeight = (int) ($photo->height ?? 0);

        if ($photoWidth <= 0 || $photoHeight <= 0) {
            $size = @getimagesize($path);
            $photoWidth = $size[0] ?? 0;
            $photoHeight = $size[1] ?? 0;
        }

        if ($photoWidth <= 0 || $photoHeight <= 0) {
            return response()->json([
                'found' => false,
            ]);
        }

        $crop = $this->computeFaceCrop($face, $photoWidth, $photoHeight, $slotWidth, $slotHeight);

        return response()->json([
            'found' => true,
            'crop' => $crop,
        ]);
    }

    protected function assetUrl(?AssetFile $assetFile): ?string
    {
        if (! $assetFile) {
            return null;
        }

        return url('storage/'.ltrim($assetFile->file_path, '/'));
    }

    protected function pickLargestFace($faces): ?array
    {
        if (! is_array($faces) || empty($faces)) {
            return null;
        }

        $best = null;
        $bestArea = 0;

        foreach ($faces as $face) {
            $rect = $this->normalizeFaceRect($face);

            if (! $rect) {
                continue;
            }

            $area = $rect['width'] * $rect['height'];

            if ($area > $bestArea) {
                $bestArea = $area;
                $best = $rect;
            }
        }

        return $best;
    }

    protected function normalizeFaceRect($face): ?array
    {
        if ($face instanceof Rect) {
            return [
                'x' => $face->x,
                'y' => $face->y,
                'width' => $face->width,
                'height' => $face->height,
            ];
        }

        if (is_array($face) && count($face) >= 4) {
            return [
                'x' => (int) $face[0],
                'y' => (int) $face[1],
                'width' => (int) $face[2],
                'height' => (int) $face[3],
            ];
        }

        if (is_object($face) && isset($face->x, $face->y, $face->width, $face->height)) {
            return [
                'x' => (int) $face->x,
                'y' => (int) $face->y,
                'width' => (int) $face->width,
                'height' => (int) $face->height,
            ];
        }

        return null;
    }

    protected function computeFaceCrop(
        array $face,
        int $photoWidth,
        int $photoHeight,
        int $slotWidth,
        int $slotHeight,
    ): array {
        $photoAspect = $photoWidth / max($photoHeight, 1);
        $slotAspect = $slotWidth / max($slotHeight, 1);
        $delta = abs(log($photoAspect / $slotAspect));

        $zoom = 1.0;

        if ($delta > 0.5) {
            $zoom = 1.25;
        } elseif ($delta > 0.25) {
            $zoom = 1.15;
        } elseif ($delta > 0.1) {
            $zoom = 1.05;
        }

        if ($photoAspect > $slotAspect) {
            $cropHeight = $photoHeight;
            $cropWidth = (int) round($photoHeight * $slotAspect);
        } else {
            $cropWidth = $photoWidth;
            $cropHeight = (int) round($photoWidth / $slotAspect);
        }

        $cropWidth = max(1, min($photoWidth, (int) round($cropWidth / $zoom)));
        $cropHeight = max(1, min($photoHeight, (int) round($cropHeight / $zoom)));

        $maxShiftX = max(0.0, ($photoWidth - $cropWidth) / 2);
        $maxShiftY = max(0.0, ($photoHeight - $cropHeight) / 2);

        $faceCenterX = $face['x'] + ($face['width'] / 2);
        $faceCenterY = $face['y'] + ($face['height'] / 2);

        $offsetX = $maxShiftX > 0
            ? (($faceCenterX - ($photoWidth / 2)) / $maxShiftX) * 100
            : 0;
        $offsetY = $maxShiftY > 0
            ? (($faceCenterY - ($photoHeight / 2)) / $maxShiftY) * 100
            : 0;

        return [
            'zoom' => round($zoom, 2),
            'offset_x' => round($this->clampOffset($offsetX)),
            'offset_y' => round($this->clampOffset($offsetY)),
        ];
    }

    protected function clampOffset(float $value): float
    {
        return max(-100, min(100, $value));
    }
}
