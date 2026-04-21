<?php

namespace Tests\Feature\Database;

use App\Models\Customer;
use App\Models\Station;
use App\Models\SubscriptionPackage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatusConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_tier_check_constraint_blocks_invalid_value(): void
    {
        $this->expectException(QueryException::class);

        Customer::query()->create([
            'id' => (string) Str::uuid(),
            'customer_whatsapp' => '6281111111111',
            'tier' => 'gold',
            'status' => 'active',
        ]);
    }

    public function test_customer_subscriptions_status_check_constraint_blocks_invalid_value(): void
    {
        $this->expectException(QueryException::class);

        $customer = Customer::query()->create([
            'id' => (string) Str::uuid(),
            'customer_whatsapp' => '6281222222222',
            'tier' => 'regular',
            'status' => 'active',
        ]);

        $package = SubscriptionPackage::query()->create([
            'id' => (string) Str::uuid(),
            'package_code' => 'PKG-TEST',
            'package_name' => 'Package Test',
            'duration_days' => 30,
            'session_quota' => 10,
            'print_quota' => 10,
            'price' => 10000,
            'is_active' => true,
        ]);

        DB::table('customer_subscriptions')->insert([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'trial',
            'start_at' => now(),
            'end_at' => now()->addDays(30),
            'auto_renew' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_photo_sessions_manual_payment_consistency_constraint_blocks_invalid_state(): void
    {
        $this->expectException(QueryException::class);

        $station = Station::query()->create([
            'id' => (string) Str::uuid(),
            'station_code' => 'ST-CS-001',
            'station_name' => 'Constraint Station',
            'status' => 'online',
        ]);

        DB::table('photo_sessions')->insert([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-CS-'.strtoupper(Str::random(6)),
            'station_id' => $station->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'total_expected_photos' => 0,
            'captured_count' => 0,
            'status' => 'created',
            'payment_status' => 'pending',
            'payment_method' => 'qris',
            'manual_payment_status' => 'pending_approval',
            'additional_print_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
