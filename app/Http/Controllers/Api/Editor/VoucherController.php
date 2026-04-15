<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\EditorVoucherQuoteRequest;
use App\Http\Requests\RevokeSessionVoucherRequest;
use App\Http\Requests\StoreManagedVoucherRequest;
use App\Http\Requests\StoreVoucherLibraryRequest;
use App\Http\Requests\UpdateVoucherLibraryRequest;
use App\Models\PhotoSession;
use App\Models\SessionVoucher;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    public function libraryIndex(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', 'all'));

        $query = Voucher::query()->latest();

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('voucher_code', 'ilike', "%{$search}%")
                    ->orWhere('voucher_type', 'ilike', "%{$search}%");
            });
        }

        $data = $query->limit(100)
            ->get()
            ->map(fn (Voucher $voucher) => $this->transformLibraryVoucher($voucher))
            ->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function libraryStore(StoreVoucherLibraryRequest $request)
    {
        $validated = $request->validated();
        $voucherCode = Str::upper(trim((string) $validated['voucher_code']));

        $existing = Voucher::query()
            ->whereRaw('lower(voucher_code) = ?', [Str::lower($voucherCode)])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Voucher code sudah digunakan.',
                'voucher' => $this->transformLibraryVoucher($existing),
            ], 422);
        }

        $voucher = Voucher::query()->create([
            'id' => (string) Str::uuid(),
            'voucher_code' => $voucherCode,
            'voucher_type' => $validated['voucher_type'],
            'status' => 'active',
            'valid_from' => $this->parseVoucherDate($validated['valid_from'] ?? null, true),
            'valid_until' => $this->parseVoucherDate($validated['valid_until'] ?? null, false),
            'max_usage' => $validated['max_usage'] ?? null,
            'used_count' => 0,
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'min_purchase_amount' => $validated['min_purchase_amount'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'metadata_json' => $validated['metadata_json'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Master voucher berhasil dibuat.',
            'voucher' => $this->transformLibraryVoucher($voucher),
        ], 201);
    }

    public function libraryDeactivate(Voucher $voucher)
    {
        if ($voucher->status === 'inactive') {
            return response()->json([
                'message' => 'Voucher sudah nonaktif.',
                'voucher' => $this->transformLibraryVoucher($voucher),
            ]);
        }

        $voucher->update([
            'status' => 'inactive',
        ]);

        return response()->json([
            'message' => 'Voucher berhasil dinonaktifkan.',
            'voucher' => $this->transformLibraryVoucher($voucher->fresh()),
        ]);
    }

    public function libraryUpdate(UpdateVoucherLibraryRequest $request, Voucher $voucher)
    {
        $validated = $request->validated();
        $voucherCode = Str::upper(trim((string) $validated['voucher_code']));

        $voucher->update([
            'voucher_code' => $voucherCode,
            'voucher_type' => $validated['voucher_type'],
            'status' => $validated['status'],
            'valid_from' => $this->parseVoucherDate($validated['valid_from'] ?? null, true),
            'valid_until' => $this->parseVoucherDate($validated['valid_until'] ?? null, false),
            'max_usage' => $validated['max_usage'] ?? null,
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'min_purchase_amount' => $validated['min_purchase_amount'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'metadata_json' => $validated['metadata_json'] ?? null,
        ]);

        return response()->json([
            'message' => 'Voucher berhasil diperbarui.',
            'voucher' => $this->transformLibraryVoucher($voucher->fresh()),
        ]);
    }

    public function libraryQuote(EditorVoucherQuoteRequest $request)
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
            'message' => 'Voucher quote generated.',
            'quote' => $quote,
            'voucher' => $voucher ? [
                'code' => $voucher->voucher_code,
                'type' => $voucher->voucher_type,
            ] : null,
        ]);
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', 'all'));

        $query = SessionVoucher::query()
            ->with(['session'])
            ->latest('applied_at')
            ->latest();

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('voucher_code', 'ilike', "%{$search}%")
                    ->orWhere('voucher_type', 'ilike', "%{$search}%")
                    ->orWhereHas('session', function ($sessionQuery) use ($search) {
                        $sessionQuery->where('session_code', 'ilike', "%{$search}%");
                    });
            });
        }

        $vouchers = $query
            ->limit(100)
            ->get()
            ->map(fn (SessionVoucher $voucher) => $this->transformVoucher($voucher))
            ->values();

        $sessionOptions = PhotoSession::query()
            ->latest()
            ->limit(50)
            ->get(['id', 'session_code', 'status'])
            ->map(function (PhotoSession $session) {
                return [
                    'id' => $session->id,
                    'session_code' => $session->session_code,
                    'status' => $session->status,
                ];
            })
            ->values();

        return response()->json([
            'data' => $vouchers,
            'session_options' => $sessionOptions,
        ]);
    }

    public function store(StoreManagedVoucherRequest $request)
    {
        $validated = $request->validated();

        $existing = SessionVoucher::query()
            ->where('session_id', $validated['session_id'])
            ->where('voucher_code', $validated['voucher_code'])
            ->where('status', 'applied')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Voucher aktif dengan kode yang sama sudah ada pada session ini.',
                'voucher' => $this->transformVoucher($existing),
            ], 200);
        }

        $voucher = DB::transaction(function () use ($request, $validated) {
            $voucher = SessionVoucher::query()->create([
                'id' => (string) Str::uuid(),
                'session_id' => $validated['session_id'],
                'voucher_code' => $validated['voucher_code'],
                'voucher_type' => $validated['voucher_type'],
                'status' => 'applied',
                'applied_by' => $request->user()?->id,
                'applied_at' => now(),
                'notes' => $validated['notes'] ?? null,
                'metadata_json' => $validated['metadata_json'] ?? null,
            ]);

            DB::table('session_events')->insert([
                'id' => (string) Str::uuid(),
                'session_id' => $validated['session_id'],
                'event_type' => 'voucher_applied',
                'actor_type' => 'user',
                'actor_id' => $request->user()?->id,
                'payload_json' => json_encode([
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $voucher->voucher_code,
                    'voucher_type' => $voucher->voucher_type,
                    'source' => 'voucher_management',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $voucher->fresh(['session']);
        });

        return response()->json([
            'message' => 'Voucher berhasil dibuat.',
            'voucher' => $this->transformVoucher($voucher),
        ], 201);
    }

    public function revoke(RevokeSessionVoucherRequest $request, SessionVoucher $voucher)
    {
        if ($voucher->status !== 'applied') {
            return response()->json([
                'message' => 'Hanya voucher aktif yang bisa dicabut.',
            ], 422);
        }

        $voucher = DB::transaction(function () use ($request, $voucher) {
            $voucher->update([
                'status' => 'revoked',
                'revoked_by' => $request->user()?->id,
                'revoked_at' => now(),
                'notes' => $request->string('notes')->toString() ?: $voucher->notes,
            ]);

            DB::table('session_events')->insert([
                'id' => (string) Str::uuid(),
                'session_id' => $voucher->session_id,
                'event_type' => 'voucher_revoked',
                'actor_type' => 'user',
                'actor_id' => $request->user()?->id,
                'payload_json' => json_encode([
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $voucher->voucher_code,
                    'voucher_type' => $voucher->voucher_type,
                    'source' => 'voucher_management',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $voucher->fresh(['session']);
        });

        return response()->json([
            'message' => 'Voucher berhasil dicabut.',
            'voucher' => $this->transformVoucher($voucher),
        ]);
    }

    private function transformVoucher(SessionVoucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'session_id' => $voucher->session_id,
            'session_code' => $voucher->session?->session_code,
            'voucher_code' => $voucher->voucher_code,
            'voucher_type' => $voucher->voucher_type,
            'status' => $voucher->status,
            'notes' => $voucher->notes,
            'applied_at' => $voucher->applied_at,
            'revoked_at' => $voucher->revoked_at,
        ];
    }

    private function transformLibraryVoucher(Voucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'voucher_code' => $voucher->voucher_code,
            'voucher_type' => $voucher->voucher_type,
            'status' => $voucher->status,
            'valid_from' => $voucher->valid_from?->format('d-m-Y'),
            'valid_until' => $voucher->valid_until?->format('d-m-Y'),
            'max_usage' => $voucher->max_usage,
            'used_count' => $voucher->used_count,
            'discount_type' => $voucher->discount_type,
            'discount_value' => $voucher->discount_value,
            'max_discount_amount' => $voucher->max_discount_amount,
            'min_purchase_amount' => $voucher->min_purchase_amount,
            'notes' => $voucher->notes,
        ];
    }

    private function parseVoucherDate(?string $date, bool $startOfDay): ?CarbonImmutable
    {
        if (! $date) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('d-m-Y', $date);

        return $startOfDay ? $parsed->startOfDay() : $parsed->endOfDay();
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

    private function isBypassVoucherType(?string $voucherType): bool
    {
        if (! $voucherType) {
            return false;
        }

        return in_array($voucherType, ['skip', 'free', 'override'], true);
    }
}
