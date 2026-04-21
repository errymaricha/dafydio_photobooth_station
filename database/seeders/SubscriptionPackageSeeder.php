<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'package_code' => 'REGULAR-30',
                'package_name' => 'Regular 30 Hari',
                'description' => 'Paket reguler untuk akses cloud basic.',
                'duration_days' => 30,
                'session_quota' => 10,
                'print_quota' => 10,
                'price' => 49000,
                'is_active' => true,
            ],
            [
                'package_code' => 'PREMIUM-30',
                'package_name' => 'Premium 30 Hari',
                'description' => 'Paket premium dengan kuota lebih tinggi.',
                'duration_days' => 30,
                'session_quota' => 50,
                'print_quota' => 100,
                'price' => 149000,
                'is_active' => true,
            ],
            [
                'package_code' => 'PREMIUM-365',
                'package_name' => 'Premium 365 Hari',
                'description' => 'Paket premium tahunan untuk kebutuhan event intensif.',
                'duration_days' => 365,
                'session_quota' => 1000,
                'print_quota' => 2000,
                'price' => 1490000,
                'is_active' => true,
            ],
        ];

        foreach ($packages as $packageData) {
            $package = SubscriptionPackage::query()->firstOrNew([
                'package_code' => $packageData['package_code'],
            ]);

            if (! $package->exists) {
                $package->id = (string) Str::uuid();
            }

            $package->fill($packageData);
            $package->save();
        }
    }
}
