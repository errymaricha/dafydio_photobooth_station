<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmSessionPaymentRequest;
use App\Http\Requests\DevicePaymentQuoteRequest;
use App\Http\Requests\DeviceStartSessionRequest;
use App\Http\Requests\DeviceVoucherVerifyRequest;
use App\Models\PhotoSession;
use App\Models\SessionVoucher;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    private const DEVICE_CONTRACT_VERSION = '2026-04-15';

    /**
     * Start a new capture session for the authenticated device.
     *
     * Success response example:
     * {"message":"Session created","session_id":"uuid","session_code":"SES-ABC12345","status":"created"}
     *
     * @response array{
     *     message: string,
     *     session_id: string,
     *     session_code: string,
     *     station_id: string,
     *     device_id: string,
     *     status: string
     * }
     */
    public function store(DeviceStartSessionRequest $request): JsonResponse
    {
        $device = $request->user();
        $validated = $request->validated();
        $voucher = null;

        if (! empty($validated['voucher_code'])) {
            $voucher = $this->resolveValidVoucher($validated['voucher_code']);

            if (! $voucher) {
                return response()->json([
                    'message' => 'Voucher tidak valid atau sudah tidak bisa digunakan.',
                ], 422);
            }
        }

        $isBypassVoucher = $this->isBypassVoucherType($voucher?->voucher_type);

        $session = DB::transaction(function () use ($device, $voucher, $isBypassVoucher) {
            $session = PhotoSession::create([
                'id' => Str::uuid(),
                'session_code' => 'SES-' . strtoupper(Str::random(8)),
                'station_id' => $device->station_id,
                'device_id' => $device->id,
                'session_type' => 'photobooth',
                'source_type' => 'android',
                'status' => 'created',
                'payment_status' => $isBypassVoucher ? 'paid' : 'pending',
                'payment_method' => $isBypassVoucher ? 'voucher' : null,
                'payment_ref' => $isBypassVoucher ? $voucher?->voucher_code : null,
                'paid_at' => $isBypassVoucher ? now() : null,
            ]);

            if ($voucher) {
                SessionVoucher::query()->create([
                    'id' => (string) Str::uuid(),
                    'session_id' => $session->id,
                    'voucher_code' => $voucher->voucher_code,
                    'voucher_type' => $voucher->voucher_type,
                    'status' => 'applied',
                    'applied_at' => now(),
                    'notes' => 'Applied during pre-payment session creation',
                    'metadata_json' => [
                        'source' => 'device_pre_payment',
                    ],
                ]);

                DB::table('vouchers')
                    ->where('id', $voucher->id)
                    ->increment('used_count');

                $this->logSessionEvent(
                    sessionId: $session->id,
                    eventType: 'voucher_applied',
                    actorType: 'device',
                    actorId: (string) $device->id,
                    payload: [
                        'source' => 'device_pre_payment',
                        'voucher_code' => $voucher->voucher_code,
                        'voucher_type' => $voucher->voucher_type,
                        'payment_status' => $isBypassVoucher ? 'paid' : 'pending',
                    ],
                );
            }

            return $session;
        });

        return response()->json([
            'message' => 'Session created',
            'contract_version' => self::DEVICE_CONTRACT_VERSION,
            'session_id' => $session->id,
            'session_code' => $session->session_code,
            'station_id' => $session->station_id,
            'device_id' => $session->device_id,
            'status' => $session->status,
            'payment_status' => $session->payment_status,
            'payment_required' => $session->payment_status !== 'paid',
            'unlock_photo' => $session->payment_status === 'paid',
            'voucher_applied' => (bool) $voucher,
            'voucher_code' => $voucher?->voucher_code,
            'voucher_type' => $voucher?->voucher_type,
            'voucher' => $voucher ? $this->mapVoucherPayload(
                voucherCode: $voucher->voucher_code,
                voucherType: $voucher->voucher_type,
            ) : null,
        ], 201);
    }

    /**
     * Verify voucher before payment step on Android.
     */
    public function verifyVoucher(DeviceVoucherVerifyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $voucher = $this->resolveValidVoucher($validated['voucher_code']);

        if (! $voucher) {
            return response()->json([
                'valid' => false,
                'unlock_photo' => false,
                'payment_required' => true,
                'voucher_code' => null,
                'voucher_type' => null,
                'message' => 'Voucher tidak valid atau sudah tidak bisa digunakan.',
            ], 422);
        }

        $isBypassVoucher = $this->isBypassVoucherType($voucher->voucher_type);
        $quote = null;

        if ($request->filled('subtotal_amount')) {
            $quote = $this->buildPaymentQuote(
                (float) $request->input('subtotal_amount'),
                $voucher
            );
        }

        return response()->json([
            'valid' => true,
            'unlock_photo' => $isBypassVoucher,
            'payment_required' => ! $isBypassVoucher,
            'contract_version' => self::DEVICE_CONTRACT_VERSION,
            'message' => $isBypassVoucher
                ? 'Voucher valid. Foto bisa langsung dimulai.'
                : 'Voucher valid. Lanjutkan ke payment dengan harga diskon.',
            'voucher_code' => $voucher->voucher_code,
            'voucher_type' => $voucher->voucher_type,
            'voucher' => $this->mapVoucherPayload(
                voucherCode: $voucher->voucher_code,
                voucherType: $voucher->voucher_type,
            ),
            'quote' => $quote,
        ]);
    }

    /**
     * Build payment quote before session creation.
     */
    public function paymentQuote(DevicePaymentQuoteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $voucher = null;

        if (! empty($validated['voucher_code'])) {
            $voucher = $this->resolveValidVoucher($validated['voucher_code']);

            if (! $voucher) {
                return response()->json([
                    'message' => 'Voucher tidak valid atau sudah tidak bisa digunakan.',
                ], 422);
            }
        }

        $quote = $this->buildPaymentQuote((float) $validated['subtotal_amount'], $voucher);

        return response()->json([
            'message' => 'Payment quote generated.',
            'contract_version' => self::DEVICE_CONTRACT_VERSION,
            'quote' => $quote,
            'voucher_code' => $voucher?->voucher_code,
            'voucher_type' => $voucher?->voucher_type,
            'voucher' => $voucher ? $this->mapVoucherPayload(
                voucherCode: $voucher->voucher_code,
                voucherType: $voucher->voucher_type,
            ) : null,
        ]);
    }

    /**
     * Check whether a session requires payment before capture continues.
     *
     * Success response example:
     * {"session_id":"uuid","payment_required":false,"skip_reason":"voucher_skip"}
     *
     * @response array{
     *     session_id: string,
     *     session_code: string,
     *     payment_required: bool,
     *     skip_reason: string|null,
     *     voucher: array{id: string, voucher_code: string, voucher_type: string, status: string}|null
     * }
     */
    public function paymentCheck(Request $request, PhotoSession $session): JsonResponse
    {
        $device = $request->user();

        if (! $device) {
            return response()->json([
                'message' => 'Unauthenticated device.',
            ], 401);
        }

        if ($session->device_id !== $device->id) {
            return response()->json([
                'message' => 'This session does not belong to this device.',
            ], 403);
        }

        $voucher = SessionVoucher::query()
            ->where('session_id', $session->id)
            ->where('status', 'applied')
            ->latest('applied_at')
            ->latest()
            ->first();

        $canSkipPayment = $this->isPaymentBypassedByVoucher($voucher);
        $paymentRequired = ! $canSkipPayment && $session->payment_status !== 'paid';

        return response()->json([
            'contract_version' => self::DEVICE_CONTRACT_VERSION,
            'session_id' => $session->id,
            'session_code' => $session->session_code,
            'payment_status' => $session->payment_status,
            'payment_required' => $paymentRequired,
            'payment_unlocked' => ! $paymentRequired,
            'skip_reason' => $canSkipPayment ? 'voucher_skip' : null,
            'voucher_code' => $voucher?->voucher_code,
            'voucher_type' => $voucher?->voucher_type,
            'voucher' => $voucher ? [
                'id' => $voucher->id,
                'voucher_code' => $voucher->voucher_code,
                'voucher_type' => $voucher->voucher_type,
                'status' => $voucher->status,
            ] : null,
        ]);
    }

    /**
     * Confirm payment for a session before capture flow.
     *
     * Success response example:
     * {"message":"Payment confirmed","session_id":"uuid","payment_status":"paid"}
     *
     * @response array{
     *     message: string,
     *     session_id: string,
     *     session_code: string,
     *     payment_status: string,
     *     payment_ref: string,
     *     payment_method: string,
     *     paid_at: string
     * }
     */
    public function confirmPayment(
        ConfirmSessionPaymentRequest $request,
        PhotoSession $session
    ): JsonResponse {
        $device = $request->user();

        if (! $device) {
            return response()->json([
                'message' => 'Unauthenticated device.',
            ], 401);
        }

        if ($session->device_id !== $device->id) {
            return response()->json([
                'message' => 'This session does not belong to this device.',
            ], 403);
        }

        $validated = $request->validated();

        $session->update([
            'payment_status' => 'paid',
            'payment_ref' => $validated['payment_ref'],
            'payment_method' => $validated['payment_method'],
            'paid_at' => now(),
        ]);

        $activeVoucher = SessionVoucher::query()
            ->where('session_id', $session->id)
            ->where('status', 'applied')
            ->latest('applied_at')
            ->latest()
            ->first();

        $this->logSessionEvent(
            sessionId: $session->id,
            eventType: 'payment_confirmed',
            actorType: 'device',
            actorId: (string) $device->id,
            payload: [
                'source' => 'device_confirm_payment',
                'payment_ref' => $validated['payment_ref'],
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'voucher_code' => $activeVoucher?->voucher_code,
                'voucher_type' => $activeVoucher?->voucher_type,
            ],
        );

        return response()->json([
            'message' => 'Payment confirmed',
            'contract_version' => self::DEVICE_CONTRACT_VERSION,
            'session_id' => $session->id,
            'session_code' => $session->session_code,
            'payment_status' => $session->payment_status,
            'payment_required' => false,
            'unlock_photo' => true,
            'payment_ref' => $session->payment_ref,
            'payment_method' => $session->payment_method,
            'paid_at' => $session->paid_at,
        ]);
    }

    /**
     * Mark a device session as completed after photo uploads are done.
     *
     * Request example:
     * {"total_expected_photos":4}
     *
     * Success response example:
     * {"message":"Session completed","session_id":"uuid","status":"uploaded","captured_count":4}
     *
     * Error response examples:
     * 401 {"message":"Unauthenticated device."}
     * 403 {"message":"This session does not belong to this device."}
     * 422 {"message":"Cannot complete session without uploaded photos."}
     *
     * @response array{
     *     message: string,
     *     session_id: string,
     *     session_code: string,
     *     status: string,
     *     captured_count: int,
     *     completed_at: string
     * }
     */
    public function complete(Request $request, PhotoSession $session): JsonResponse
    {
        $device = $request->user();

        if (!$device) {
            return response()->json([
                'message' => 'Unauthenticated device.',
            ], 401);
        }

        if ($session->device_id !== $device->id) {
            return response()->json([
                'message' => 'This session does not belong to this device.',
            ], 403);
        }

        if ($session->captured_count < 1) {
            return response()->json([
                'message' => 'Cannot complete session without uploaded photos.',
            ], 422);
        }

        if (! $this->isSessionPaymentUnlocked($session)) {
            return response()->json([
                'message' => 'Payment is required before completing this session.',
            ], 422);
        }

        $session->update([
            'status' => 'uploaded',
            'completed_at' => now(),
            'captured_at' => $session->captured_at ?? now(),
            'total_expected_photos' => (int) $request->input('total_expected_photos', $session->captured_count),
        ]);

        return response()->json([
            'message' => 'Session completed',
            'session_id' => $session->id,
            'session_code' => $session->session_code,
            'status' => $session->status,
            'captured_count' => $session->captured_count,
            'completed_at' => $session->completed_at,
        ]);
    }

    private function isPaymentBypassedByVoucher(?SessionVoucher $voucher): bool
    {
        if (! $voucher) {
            return false;
        }

        $skipVoucherTypes = ['skip', 'free', 'override'];

        return in_array((string) $voucher->voucher_type, $skipVoucherTypes, true);
    }

    private function isBypassVoucherType(?string $voucherType): bool
    {
        if (! $voucherType) {
            return false;
        }

        return in_array($voucherType, ['skip', 'free', 'override'], true);
    }

    private function isSessionPaymentUnlocked(PhotoSession $session): bool
    {
        if ($session->payment_status === 'paid') {
            return true;
        }

        $activeVoucher = SessionVoucher::query()
            ->where('session_id', $session->id)
            ->where('status', 'applied')
            ->latest('applied_at')
            ->latest()
            ->first();

        return $this->isPaymentBypassedByVoucher($activeVoucher);
    }

    private function resolveValidVoucher(string $voucherCode): ?Voucher
    {
        $now = now();

        return Voucher::query()
            ->whereRaw('lower(voucher_code) = ?', [mb_strtolower(trim($voucherCode))])
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $now);
            })
            ->where(function ($query) {
                $query->whereNull('max_usage')
                    ->orWhereColumn('used_count', '<', 'max_usage');
            })
            ->first();
    }

    private function buildPaymentQuote(float $subtotalAmount, ?Voucher $voucher): array
    {
        $subtotal = max(0, round($subtotalAmount, 2));
        $discount = 0.0;
        $discountReason = null;
        $isBypassVoucher = $this->isBypassVoucherType($voucher?->voucher_type);

        if ($voucher) {
            $minimumPurchase = $voucher->min_purchase_amount !== null
                ? (float) $voucher->min_purchase_amount
                : null;

            if ($minimumPurchase !== null && $subtotal < $minimumPurchase) {
                $discountReason = 'min_purchase_not_met';
            } else {
                if ($voucher->discount_type === 'percent') {
                    $discount = $subtotal * (((float) $voucher->discount_value) / 100);
                }

                if ($voucher->discount_type === 'fixed') {
                    $discount = (float) $voucher->discount_value;
                }

                if ($isBypassVoucher && $discount <= 0) {
                    $discount = $subtotal;
                }

                if ($voucher->max_discount_amount !== null) {
                    $discount = min($discount, (float) $voucher->max_discount_amount);
                }
            }
        }

        $discount = min($discount, $subtotal);
        $totalDue = round($subtotal - $discount, 2);
        $paymentRequired = ! $isBypassVoucher && $totalDue > 0;

        return [
            'subtotal_amount' => round($subtotal, 2),
            'discount_amount' => round($discount, 2),
            'total_due' => $totalDue,
            'payment_required' => $paymentRequired,
            'unlock_photo' => ! $paymentRequired,
            'discount_reason' => $discountReason,
        ];
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

    /**
     * @return array{code: string, type: string, voucher_code: string, voucher_type: string}
     */
    private function mapVoucherPayload(string $voucherCode, string $voucherType): array
    {
        return [
            'code' => $voucherCode,
            'type' => $voucherType,
            'voucher_code' => $voucherCode,
            'voucher_type' => $voucherType,
        ];
    }
}
