<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VoucherLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('email', 'admin@photobooth.local')
            ->first();

        if (! $admin) {
            return;
        }

        $today = CarbonImmutable::today();

        $this->upsertVoucher(
            voucherCode: 'VCH-PROMO-10',
            payload: [
                'voucher_type' => 'promo',
                'status' => 'active',
                'valid_from' => $today->subDays(3)->startOfDay(),
                'valid_until' => $today->addDays(14)->endOfDay(),
                'max_usage' => 300,
                'used_count' => 21,
                'discount_type' => 'percent',
                'discount_value' => 10,
                'max_discount_amount' => 15000,
                'min_purchase_amount' => 50000,
                'notes' => 'Promo reguler diskon 10%.',
                'metadata_json' => [
                    'source' => 'seed',
                    'campaign' => 'regular-discount',
                ],
            ],
            createdBy: $admin->id,
        );

        $this->upsertVoucher(
            voucherCode: 'VCH-PROMO-20K',
            payload: [
                'voucher_type' => 'promo',
                'status' => 'active',
                'valid_from' => $today->subDays(1)->startOfDay(),
                'valid_until' => $today->addDays(10)->endOfDay(),
                'max_usage' => 120,
                'used_count' => 9,
                'discount_type' => 'fixed',
                'discount_value' => 20000,
                'max_discount_amount' => null,
                'min_purchase_amount' => 100000,
                'notes' => 'Potongan nominal Rp20.000.',
                'metadata_json' => [
                    'source' => 'seed',
                    'campaign' => 'fixed-discount',
                ],
            ],
            createdBy: $admin->id,
        );

        $this->upsertVoucher(
            voucherCode: 'VCH-SKIP-LOCAL',
            payload: [
                'voucher_type' => 'skip',
                'status' => 'active',
                'valid_from' => $today->subDays(7)->startOfDay(),
                'valid_until' => $today->addDays(30)->endOfDay(),
                'max_usage' => null,
                'used_count' => 2,
                'discount_type' => null,
                'discount_value' => null,
                'max_discount_amount' => null,
                'min_purchase_amount' => null,
                'notes' => 'Bypass payment untuk operasional lokal.',
                'metadata_json' => [
                    'source' => 'seed',
                    'campaign' => 'ops-bypass',
                ],
            ],
            createdBy: $admin->id,
        );

        $this->upsertVoucher(
            voucherCode: 'VCH-FREE-EVENT',
            payload: [
                'voucher_type' => 'free',
                'status' => 'active',
                'valid_from' => $today->subDays(2)->startOfDay(),
                'valid_until' => $today->addDays(5)->endOfDay(),
                'max_usage' => 50,
                'used_count' => 6,
                'discount_type' => 'percent',
                'discount_value' => 100,
                'max_discount_amount' => null,
                'min_purchase_amount' => 0,
                'notes' => 'Voucher gratis untuk event tertentu.',
                'metadata_json' => [
                    'source' => 'seed',
                    'campaign' => 'event-free',
                ],
            ],
            createdBy: $admin->id,
        );

        $this->upsertVoucher(
            voucherCode: 'VCH-OVERRIDE-QA',
            payload: [
                'voucher_type' => 'override',
                'status' => 'inactive',
                'valid_from' => $today->subDays(30)->startOfDay(),
                'valid_until' => $today->subDays(1)->endOfDay(),
                'max_usage' => 30,
                'used_count' => 30,
                'discount_type' => null,
                'discount_value' => null,
                'max_discount_amount' => null,
                'min_purchase_amount' => null,
                'notes' => 'Voucher override QA (sudah nonaktif).',
                'metadata_json' => [
                    'source' => 'seed',
                    'campaign' => 'qa-expired',
                ],
            ],
            createdBy: $admin->id,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertVoucher(string $voucherCode, array $payload, string $createdBy): void
    {
        $voucher = Voucher::query()->firstOrNew([
            'voucher_code' => Str::upper($voucherCode),
        ]);

        if (! $voucher->exists) {
            $voucher->id = (string) Str::uuid();
        }

        $voucher->fill(array_merge($payload, [
            'created_by' => $voucher->created_by ?? $createdBy,
        ]));
        $voucher->save();
    }
}
