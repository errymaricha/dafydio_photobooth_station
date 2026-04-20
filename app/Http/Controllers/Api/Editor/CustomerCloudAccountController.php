<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertCustomerCloudAccountRequest;
use App\Models\Customer;
use App\Models\CustomerCloudAccount;
use App\Models\CustomerSubscription;
use App\Models\PhotoSession;
use App\Support\CustomerIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerCloudAccountController extends Controller
{
    public function __construct(
        private CustomerIdentity $customerIdentity
    ) {}

    public function index(): JsonResponse
    {
        $summaries = PhotoSession::query()
            ->selectRaw(
                "customer_whatsapp, count(*) as sessions_count, sum(case when payment_status = 'paid' then 1 else 0 end) as paid_sessions_count, coalesce(sum(captured_count), 0) as captured_photos_count, max(created_at) as latest_session_at"
            )
            ->whereNotNull('customer_whatsapp')
            ->where('customer_whatsapp', '!=', '')
            ->groupBy('customer_whatsapp')
            ->orderByDesc('latest_session_at')
            ->get();

        $accounts = CustomerCloudAccount::query()
            ->whereIn('customer_whatsapp', $summaries->pluck('customer_whatsapp')->all())
            ->get()
            ->keyBy('customer_whatsapp');
        $customers = Customer::query()
            ->whereIn('customer_whatsapp', $summaries->pluck('customer_whatsapp')->all())
            ->get()
            ->keyBy('customer_whatsapp');

        return response()->json([
            'customers' => $summaries->map(function ($summary) use ($accounts, $customers): array {
                $account = $accounts->get($summary->customer_whatsapp);
                $customer = $customers->get($summary->customer_whatsapp);

                return [
                    'customer_id' => $summary->customer_whatsapp,
                    'customer_whatsapp' => $summary->customer_whatsapp,
                    'username' => $account?->cloud_username ?? $summary->customer_whatsapp,
                    'tier' => $customer?->tier ?? 'regular',
                    'has_active_subscription' => $this->hasActiveSubscription($customer?->id),
                    'has_cloud_password' => (bool) $account?->password_set_at,
                    'account_status' => $account?->status,
                    'password_set_at' => $account?->password_set_at,
                    'sessions_count' => (int) $summary->sessions_count,
                    'paid_sessions_count' => (int) $summary->paid_sessions_count,
                    'captured_photos_count' => (int) $summary->captured_photos_count,
                    'latest_session_at' => $summary->latest_session_at,
                ];
            })->values(),
        ]);
    }

    public function upsert(UpsertCustomerCloudAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $customerWhatsapp = $this->customerIdentity->normalizeWhatsapp($validated['customer_whatsapp']);

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

        $customer = $this->customerIdentity->resolveOrCreateCustomerByWhatsapp($customerWhatsapp);

        if ($customer) {
            $customer->fill([
                'tier' => $customer->tier ?: 'regular',
                'status' => $customer->status ?: 'active',
                'cloud_account_id' => $account->id,
            ]);
            $customer->save();
        }

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
        $normalized = $this->customerIdentity->normalizeWhatsapp($customerWhatsapp) ?? '';
        $payload = $this->buildCustomerArchivePayload($normalized);

        return response()->json([
            'customer' => [
                'customer_id' => $normalized,
                'customer_whatsapp' => $normalized,
                'username' => $payload['username'],
                'tier' => $payload['customer_tier'],
                'active_subscription' => $payload['active_subscription'],
                'has_cloud_password' => $payload['has_cloud_password'],
                'password_set_at' => $payload['password_set_at'],
                'account_status' => $payload['account_status'],
            ],
            'summary' => [
                'sessions_count' => $payload['sessions_count'],
                'paid_sessions_count' => $payload['paid_sessions_count'],
                'photos_count' => $payload['photos_count'],
                'rendered_outputs_count' => $payload['rendered_outputs_count'],
            ],
            'sessions' => $payload['sessions'],
        ]);
    }

    public function cloudSync(string $customerWhatsapp): JsonResponse
    {
        $normalized = $this->customerIdentity->normalizeWhatsapp($customerWhatsapp) ?? '';
        $payload = $this->buildCustomerArchivePayload($normalized);

        return response()->json([
            'customer_id' => $normalized,
            'customer_whatsapp' => $normalized,
            'username' => $payload['username'],
            'customer_tier' => $payload['customer_tier'],
            'active_subscription' => $payload['active_subscription'],
            'has_cloud_password' => $payload['has_cloud_password'],
            'password_set_at' => $payload['password_set_at'],
            'account_status' => $payload['account_status'],
            'total_sessions' => $payload['sessions_count'],
            'paid_sessions_count' => $payload['paid_sessions_count'],
            'total_photos' => $payload['photos_count'],
            'total_rendered_outputs' => $payload['rendered_outputs_count'],
            'sessions' => $payload['sessions'],
        ]);
    }

    /**
     * @return array{
     *     username: string,
     *     customer_tier: string,
     *     active_subscription: array<string, mixed>|null,
     *     has_cloud_password: bool,
     *     password_set_at: mixed,
     *     account_status: string|null,
     *     sessions_count: int,
     *     paid_sessions_count: int,
     *     photos_count: int,
     *     rendered_outputs_count: int,
     *     sessions: Collection<int, array<string, mixed>>
     * }
     */
    private function buildCustomerArchivePayload(string $normalized): array
    {
        $sessions = PhotoSession::query()
            ->where('customer_whatsapp', $normalized)
            ->with([
                'station',
                'photos.originalFile',
                'photos.thumbnailFile',
                'renderedOutputs.file',
            ])
            ->latest('created_at')
            ->get();

        $account = CustomerCloudAccount::query()
            ->where('customer_whatsapp', $normalized)
            ->first();
        $customer = Customer::query()
            ->where('customer_whatsapp', $normalized)
            ->first();

        $totalPhotos = $sessions->sum(fn (PhotoSession $session): int => $session->photos->count());
        $totalRenderedOutputs = $sessions->sum(
            fn (PhotoSession $session): int => $session->renderedOutputs->count()
        );
        $activeSubscription = $customer
            ? $this->activeSubscriptionPayload($customer->id)
            : null;

        return [
            'username' => $account?->cloud_username ?? $normalized,
            'customer_tier' => $customer?->tier ?? 'regular',
            'active_subscription' => $activeSubscription,
            'has_cloud_password' => (bool) $account?->password_set_at,
            'password_set_at' => $account?->password_set_at,
            'account_status' => $account?->status,
            'sessions_count' => $sessions->count(),
            'paid_sessions_count' => $sessions->where('payment_status', 'paid')->count(),
            'photos_count' => $totalPhotos,
            'rendered_outputs_count' => $totalRenderedOutputs,
            'sessions' => $sessions->map(function (PhotoSession $session): array {
                return [
                    'session_id' => $session->id,
                    'session_code' => $session->session_code,
                    'station_id' => $session->station_id,
                    'station_code' => $session->station?->station_code,
                    'device_id' => $session->device_id,
                    'status' => $session->status,
                    'payment_status' => $session->payment_status,
                    'payment_method' => $session->payment_method,
                    'created_at' => $session->created_at,
                    'completed_at' => $session->completed_at,
                    'paid_at' => $session->paid_at,
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
                            'original_file' => $photo->originalFile ? [
                                'file_id' => $photo->originalFile->id,
                                'storage_disk' => $photo->originalFile->storage_disk,
                                'file_path' => $photo->originalFile->file_path,
                                'file_url' => $this->assetUrl(
                                    $photo->originalFile->storage_disk,
                                    $photo->originalFile->file_path,
                                ),
                                'file_name' => $photo->originalFile->file_name,
                                'file_ext' => $photo->originalFile->file_ext,
                                'mime_type' => $photo->originalFile->mime_type,
                                'file_size_bytes' => $photo->originalFile->file_size_bytes,
                                'checksum_sha256' => $photo->originalFile->checksum_sha256,
                            ] : null,
                            'thumbnail_file' => $photo->thumbnailFile ? [
                                'file_id' => $photo->thumbnailFile->id,
                                'storage_disk' => $photo->thumbnailFile->storage_disk,
                                'file_path' => $photo->thumbnailFile->file_path,
                                'file_url' => $this->assetUrl(
                                    $photo->thumbnailFile->storage_disk,
                                    $photo->thumbnailFile->file_path,
                                ),
                                'file_name' => $photo->thumbnailFile->file_name,
                                'file_ext' => $photo->thumbnailFile->file_ext,
                                'mime_type' => $photo->thumbnailFile->mime_type,
                                'file_size_bytes' => $photo->thumbnailFile->file_size_bytes,
                                'checksum_sha256' => $photo->thumbnailFile->checksum_sha256,
                            ] : null,
                            // Backward compatible field for existing Android/cloud parser.
                            'file' => $photo->originalFile ? [
                                'file_id' => $photo->originalFile->id,
                                'storage_disk' => $photo->originalFile->storage_disk,
                                'file_path' => $photo->originalFile->file_path,
                                'file_url' => $this->assetUrl(
                                    $photo->originalFile->storage_disk,
                                    $photo->originalFile->file_path,
                                ),
                                'file_name' => $photo->originalFile->file_name,
                                'file_ext' => $photo->originalFile->file_ext,
                                'mime_type' => $photo->originalFile->mime_type,
                                'file_size_bytes' => $photo->originalFile->file_size_bytes,
                                'checksum_sha256' => $photo->originalFile->checksum_sha256,
                            ] : null,
                        ];
                    })->values(),
                    'rendered_outputs' => $session->renderedOutputs
                        ->sortByDesc('version_no')
                        ->values()
                        ->map(function ($output): array {
                            return [
                                'rendered_output_id' => $output->id,
                                'version_no' => $output->version_no,
                                'is_active' => (bool) $output->is_active,
                                'rendered_at' => $output->rendered_at,
                                'file' => $output->file ? [
                                    'file_id' => $output->file->id,
                                    'storage_disk' => $output->file->storage_disk,
                                    'file_path' => $output->file->file_path,
                                    'file_url' => $this->assetUrl(
                                        $output->file->storage_disk,
                                        $output->file->file_path,
                                    ),
                                    'file_name' => $output->file->file_name,
                                    'file_ext' => $output->file->file_ext,
                                    'mime_type' => $output->file->mime_type,
                                    'file_size_bytes' => $output->file->file_size_bytes,
                                    'checksum_sha256' => $output->file->checksum_sha256,
                                ] : null,
                            ];
                        }),
                ];
            }),
        ];
    }

    private function assetUrl(?string $disk, ?string $path): ?string
    {
        if (! $disk || ! $path) {
            return null;
        }

        return Storage::disk($disk)->url($path);
    }

    private function hasActiveSubscription(?string $customerId): bool
    {
        if (! $customerId) {
            return false;
        }

        return CustomerSubscription::query()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            })
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeSubscriptionPayload(string $customerId): ?array
    {
        $subscription = CustomerSubscription::query()
            ->with('package')
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            })
            ->latest('start_at')
            ->first();

        if (! $subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'start_at' => $subscription->start_at,
            'end_at' => $subscription->end_at,
            'package_code' => $subscription->package?->package_code,
            'package_name' => $subscription->package?->package_name,
        ];
    }
}
