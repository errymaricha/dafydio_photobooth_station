<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplySessionVoucherRequest;
use App\Http\Requests\RevokeSessionVoucherRequest;
use App\Models\PhotoSession;
use App\Models\SessionVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionVoucherController extends Controller
{
    public function store(ApplySessionVoucherRequest $request, PhotoSession $session)
    {
        $existing = $session->vouchers()
            ->where('voucher_code', $request->string('voucher_code')->toString())
            ->where('status', 'applied')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Voucher sudah diterapkan pada session ini.',
                'voucher' => $this->transformVoucher($existing),
            ], 200);
        }

        $voucher = DB::transaction(function () use ($request, $session) {
            $voucher = SessionVoucher::create([
                'id' => (string) Str::uuid(),
                'session_id' => $session->id,
                'voucher_code' => $request->string('voucher_code')->toString(),
                'voucher_type' => $request->string('voucher_type')->toString(),
                'status' => 'applied',
                'applied_by' => $request->user()?->id,
                'applied_at' => now(),
                'notes' => $request->string('notes')->toString() ?: null,
                'metadata_json' => $request->input('metadata_json'),
            ]);

            DB::table('session_events')->insert([
                'id' => (string) Str::uuid(),
                'session_id' => $session->id,
                'event_type' => 'voucher_applied',
                'actor_type' => 'user',
                'actor_id' => $request->user()?->id,
                'payload_json' => json_encode([
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $voucher->voucher_code,
                    'voucher_type' => $voucher->voucher_type,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $voucher;
        });

        return response()->json([
            'message' => 'Voucher diterapkan ke session.',
            'voucher' => $this->transformVoucher($voucher),
        ], 201);
    }

    public function revoke(
        RevokeSessionVoucherRequest $request,
        PhotoSession $session,
        SessionVoucher $voucher
    ) {
        if ($voucher->session_id !== $session->id) {
            return response()->json([
                'message' => 'Voucher tidak sesuai dengan session.',
            ], 404);
        }

        if ($voucher->status !== 'applied') {
            return response()->json([
                'message' => 'Hanya voucher aktif yang bisa dicabut.',
            ], 422);
        }

        $voucher = DB::transaction(function () use ($request, $session, $voucher) {
            $voucher->update([
                'status' => 'revoked',
                'revoked_by' => $request->user()?->id,
                'revoked_at' => now(),
                'notes' => $request->string('notes')->toString() ?: $voucher->notes,
            ]);

            DB::table('session_events')->insert([
                'id' => (string) Str::uuid(),
                'session_id' => $session->id,
                'event_type' => 'voucher_revoked',
                'actor_type' => 'user',
                'actor_id' => $request->user()?->id,
                'payload_json' => json_encode([
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $voucher->voucher_code,
                    'voucher_type' => $voucher->voucher_type,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $voucher->fresh();
        });

        return response()->json([
            'message' => 'Voucher dicabut dari session.',
            'voucher' => $this->transformVoucher($voucher),
        ], 200);
    }

    private function transformVoucher(SessionVoucher $voucher): array
    {
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
    }
}
