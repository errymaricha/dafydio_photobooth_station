<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceUploadPhotoRequest;
use App\Jobs\GenerateThumbnailJob;
use App\Models\AssetFile;
use App\Models\PhotoSession;
use App\Models\SessionPhoto;
use App\Models\SessionVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Persist one uploaded photo for the authenticated device session.
     *
     * Request example (`multipart/form-data`):
     * photo=<binary>, capture_index=1, slot_index=1
     *
     * Success response example:
     * {"message":"Photo uploaded","session_photo_id":"uuid","asset_file_id":"uuid","capture_index":1}
     *
     * Error response examples:
     * 401 {"message":"Unauthenticated device"}
     * 403 {"message":"This session does not belong to this device."}
     * 409 {"message":"This capture index has already been uploaded for the session."}
     *
     * @response array{
     *     message: string,
     *     session_photo_id: string,
     *     asset_file_id: string,
     *     capture_index: int,
     *     file_path: string
     * }
     */
    public function store(DeviceUploadPhotoRequest $request, PhotoSession $session): JsonResponse
    {
        $validated = $request->validated();
        $device = $request->user();

        if (!$device) {
            return response()->json([
                'message' => 'Unauthenticated device',
            ], 401);
        }

        if ($session->device_id !== $device->id) {
            return response()->json([
                'message' => 'This session does not belong to this device.',
            ], 403);
        }

        $activeVoucher = SessionVoucher::query()
            ->where('session_id', $session->id)
            ->where('status', 'applied')
            ->latest('applied_at')
            ->latest()
            ->first();
        $skipVoucherTypes = ['skip', 'free', 'override'];
        $paymentBypassed = $activeVoucher !== null
            && in_array((string) $activeVoucher->voucher_type, $skipVoucherTypes, true);

        if (! $paymentBypassed && $session->payment_status !== 'paid') {
            $this->logSessionEvent(
                sessionId: $session->id,
                eventType: 'payment_gate_blocked',
                actorType: 'device',
                actorId: (string) $device->id,
                payload: [
                    'source' => 'device_upload_guard',
                    'reason' => 'payment_required',
                    'payment_status' => $session->payment_status,
                    'voucher_code' => $activeVoucher?->voucher_code,
                    'voucher_type' => $activeVoucher?->voucher_type,
                    'capture_index' => (int) $validated['capture_index'],
                ],
            );

            return response()->json([
                'message' => 'Payment is required before uploading photos.',
            ], 422);
        }

        if (
            SessionPhoto::query()
                ->where('session_id', $session->id)
                ->where('capture_index', $validated['capture_index'])
                ->exists()
        ) {
            return response()->json([
                'message' => 'This capture index has already been uploaded for the session.',
            ], 409);
        }

        $session->load(['station', 'device']);

        if (!$session->station) {
            return response()->json([
                'message' => 'Station not found',
            ], 500);
        }

        $file = $request->file('photo');

        if (!$file) {
            return response()->json([
                'message' => 'Photo file missing',
            ], 422);
        }

        $datePath = now()->format('Y/m/d');
        $dir = "stations/{$session->station->station_code}/sessions/{$datePath}/{$session->session_code}/original";
        $ext = $file->getClientOriginalExtension();
        $deviceShort = strtoupper(substr($session->device->device_code ?? 'DEV', 0, 4));

        $fileName = $deviceShort . '_'
            . str_pad((string) $validated['capture_index'], 2, '0', STR_PAD_LEFT)
            . '.' . $ext;

        if (Storage::disk('public')->exists($dir . '/' . $fileName)) {
            $fileName = $deviceShort . '_'
                . str_pad((string) $validated['capture_index'], 2, '0', STR_PAD_LEFT)
                . '_' . substr((string) Str::uuid(), 0, 4)
                . '.' . $ext;
        }

        $storedPath = $file->storeAs($dir, $fileName, 'public');

        $asset = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => $storedPath,
            'file_name' => $fileName,
            'file_ext' => $ext,
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'file_category' => 'original',
            'created_by_type' => 'device',
            'created_by_id' => $device->id,
        ]);

        $sessionPhoto = SessionPhoto::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'capture_index' => (int) $validated['capture_index'],
            'slot_index' => $validated['slot_index'] ?? null,
            'original_file_id' => $asset->id,
            'file_size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'is_selected' => true,
            'uploaded_at' => now(),
        ]);

        $session->increment('captured_count');
        GenerateThumbnailJob::dispatch($sessionPhoto->id);

        return response()->json([
            'message' => 'Photo uploaded',
            'session_photo_id' => $sessionPhoto->id,
            'asset_file_id' => $asset->id,
            'capture_index' => $sessionPhoto->capture_index,
            'file_path' => $storedPath,
        ], 201);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function logSessionEvent(
        string $sessionId,
        string $eventType,
        ?string $actorType = null,
        ?string $actorId = null,
        ?array $payload = null
    ): void {
        DB::table('session_events')->insert([
            'id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'event_type' => $eventType,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'payload_json' => $payload ? json_encode($payload) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
