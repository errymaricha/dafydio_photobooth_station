<?php

namespace Database\Seeders;

use App\Models\PhotoSession;
use App\Models\SessionVoucher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionVoucherSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('email', 'admin@photobooth.local')
            ->first();

        if (! $admin) {
            return;
        }

        $sessionCodes = [
            'SES-DEMO-UPLOADED',
            'SES-DEMO-EDITING',
            'SES-DEMO-READY',
            'SES-DEMO-QUEUED',
            'SES-DEMO-PRINTED',
        ];

        $sessions = PhotoSession::query()
            ->whereIn('session_code', $sessionCodes)
            ->get()
            ->keyBy('session_code');

        DB::transaction(function () use ($admin, $sessions): void {
            $this->seedVoucher(
                adminId: $admin->id,
                session: $sessions->get('SES-DEMO-UPLOADED'),
                code: 'VCH-PROMO-10',
                type: 'promo',
                status: 'applied',
                notes: 'Promo 10% dipakai pada sesi uploaded demo',
            );

            $this->seedVoucher(
                adminId: $admin->id,
                session: $sessions->get('SES-DEMO-EDITING'),
                code: 'VCH-SKIP-LOCAL',
                type: 'skip',
                status: 'applied',
                notes: 'Skip voucher demo',
            );

            $this->seedVoucher(
                adminId: $admin->id,
                session: $sessions->get('SES-DEMO-READY'),
                code: 'VCH-PROMO-20K',
                type: 'promo',
                status: 'revoked',
                notes: 'Promo voucher revoked demo',
            );

            $this->seedVoucher(
                adminId: $admin->id,
                session: $sessions->get('SES-DEMO-QUEUED'),
                code: 'VCH-OVERRIDE-QA',
                type: 'override',
                status: 'applied',
                notes: 'Override voucher applied pada sesi queued demo',
            );

            $this->seedVoucher(
                adminId: $admin->id,
                session: $sessions->get('SES-DEMO-PRINTED'),
                code: 'VCH-FREE-EVENT',
                type: 'free',
                status: 'applied',
                notes: 'Free voucher demo pada sesi printed',
            );
        });
    }

    private function seedVoucher(
        string $adminId,
        ?PhotoSession $session,
        string $code,
        string $type,
        string $status,
        string $notes
    ): void {
        if (! $session) {
            return;
        }

        $voucher = SessionVoucher::query()->firstOrNew([
            'session_id' => $session->id,
            'voucher_code' => $code,
            'status' => $status,
        ]);

        if (! $voucher->exists) {
            $voucher->id = (string) Str::uuid();
        }

        $voucher->fill([
            'voucher_type' => $type,
            'notes' => $notes,
            'applied_by' => $adminId,
            'applied_at' => $voucher->applied_at ?? now(),
            'revoked_by' => $status === 'revoked' ? $adminId : null,
            'revoked_at' => $status === 'revoked' ? ($voucher->revoked_at ?? now()) : null,
        ]);

        $voucher->save();
    }
}
