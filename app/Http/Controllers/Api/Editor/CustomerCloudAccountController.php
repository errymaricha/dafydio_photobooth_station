<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertCustomerCloudAccountRequest;
use App\Models\CustomerCloudAccount;
use App\Models\PhotoSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerCloudAccountController extends Controller
{
    public function upsert(UpsertCustomerCloudAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $customerWhatsapp = $this->normalizeWhatsapp($validated['customer_whatsapp']);

        $account = CustomerCloudAccount::query()->firstOrNew([
            'customer_whatsapp' => $customerWhatsapp,
        ]);

        if (! $account->exists) {
            $account->id = (string) Str::uuid();
        }

        $account->fill([
            'cloud_username' => $customerWhatsapp,
            'cloud_password_hash' => Hash::make($validated['password']),
            'password_set_at' => now(),
            'status' => 'active',
        ]);
        $account->save();

        return response()->json([
            'message' => 'Customer cloud credential saved.',
            'customer_id' => $account->cloud_username,
            'customer_whatsapp' => $account->customer_whatsapp,
            'username' => $account->cloud_username,
            'status' => $account->status,
            'password_set_at' => $account->password_set_at,
        ]);
    }

    public function history(string $customerWhatsapp): JsonResponse
    {
        $normalized = $this->normalizeWhatsapp($customerWhatsapp);

        $sessions = PhotoSession::query()
            ->where('customer_whatsapp', $normalized)
            ->with(['photos.originalFile'])
            ->latest('created_at')
            ->get();

        $account = CustomerCloudAccount::query()
            ->where('customer_whatsapp', $normalized)
            ->first();

        $totalPhotos = $sessions->sum(fn (PhotoSession $session): int => $session->photos->count());

        return response()->json([
            'customer' => [
                'customer_id' => $normalized,
                'customer_whatsapp' => $normalized,
                'username' => $account?->cloud_username ?? $normalized,
                'has_cloud_password' => (bool) $account?->password_set_at,
                'password_set_at' => $account?->password_set_at,
                'account_status' => $account?->status,
            ],
            'summary' => [
                'sessions_count' => $sessions->count(),
                'paid_sessions_count' => $sessions->where('payment_status', 'paid')->count(),
                'photos_count' => $totalPhotos,
            ],
            'sessions' => $sessions->map(function (PhotoSession $session): array {
                return [
                    'session_id' => $session->id,
                    'session_code' => $session->session_code,
                    'station_id' => $session->station_id,
                    'device_id' => $session->device_id,
                    'status' => $session->status,
                    'payment_status' => $session->payment_status,
                    'payment_method' => $session->payment_method,
                    'created_at' => $session->created_at,
                    'completed_at' => $session->completed_at,
                    'photos' => $session->photos->map(function ($photo): array {
                        return [
                            'photo_id' => $photo->id,
                            'capture_index' => $photo->capture_index,
                            'slot_index' => $photo->slot_index,
                            'uploaded_at' => $photo->uploaded_at,
                            'width' => $photo->width,
                            'height' => $photo->height,
                            'mime_type' => $photo->mime_type,
                            'file_size_bytes' => $photo->file_size_bytes,
                            'checksum_sha256' => $photo->checksum_sha256,
                            'file' => $photo->originalFile ? [
                                'file_id' => $photo->originalFile->id,
                                'storage_disk' => $photo->originalFile->storage_disk,
                                'file_path' => $photo->originalFile->file_path,
                                'file_name' => $photo->originalFile->file_name,
                                'file_ext' => $photo->originalFile->file_ext,
                                'mime_type' => $photo->originalFile->mime_type,
                                'file_size_bytes' => $photo->originalFile->file_size_bytes,
                                'checksum_sha256' => $photo->originalFile->checksum_sha256,
                            ] : null,
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    private function normalizeWhatsapp(string $input): string
    {
        $digits = preg_replace('/\D+/', '', trim($input)) ?? '';

        if ($digits === '') {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }
}
