<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_upgrade_customer_to_premium_subscription(): void
    {
        $editor = $this->createEditorUser();
        Sanctum::actingAs($editor);

        $customer = Customer::query()->create([
            'id' => (string) Str::uuid(),
            'customer_whatsapp' => '6281234567890',
            'tier' => 'regular',
            'status' => 'active',
        ]);

        $package = SubscriptionPackage::query()->create([
            'id' => (string) Str::uuid(),
            'package_code' => 'PREMIUM-30',
            'package_name' => 'Premium 30 Hari',
            'duration_days' => 30,
            'session_quota' => 100,
            'print_quota' => 200,
            'price' => 199000,
            'is_active' => true,
        ]);

        $this->postJson('/api/editor/customers/0812-3456-7890/subscriptions/upgrade', [
            'package_code' => 'PREMIUM-30',
            'notes' => 'Upgrade by operator',
        ])
            ->assertOk()
            ->assertJsonPath('customer_tier', 'premium')
            ->assertJsonPath('subscription.package_code', 'PREMIUM-30');

        $this->assertDatabaseHas('customer_subscriptions', [
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'tier' => 'premium',
        ]);
    }

    public function test_editor_can_downgrade_customer_to_regular(): void
    {
        $editor = $this->createEditorUser();
        Sanctum::actingAs($editor);

        $customer = Customer::query()->create([
            'id' => (string) Str::uuid(),
            'customer_whatsapp' => '6281234567890',
            'tier' => 'premium',
            'status' => 'active',
        ]);

        $package = SubscriptionPackage::query()->create([
            'id' => (string) Str::uuid(),
            'package_code' => 'PREMIUM-30',
            'package_name' => 'Premium 30 Hari',
            'duration_days' => 30,
            'session_quota' => 100,
            'print_quota' => 200,
            'price' => 199000,
            'is_active' => true,
        ]);

        CustomerSubscription::query()->create([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(29),
            'auto_renew' => false,
        ]);

        $this->postJson('/api/editor/customers/0812-3456-7890/subscriptions/downgrade', [
            'reason' => 'Customer request',
        ])
            ->assertOk()
            ->assertJsonPath('customer_tier', 'regular')
            ->assertJsonPath('cancelled_subscriptions', 1);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'tier' => 'regular',
        ]);

        $this->assertDatabaseHas('customer_subscriptions', [
            'customer_id' => $customer->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_upgrade_links_to_previous_active_subscription_and_keeps_single_active(): void
    {
        $editor = $this->createEditorUser();
        Sanctum::actingAs($editor);

        $customer = Customer::query()->create([
            'id' => (string) Str::uuid(),
            'customer_whatsapp' => '6281234567890',
            'tier' => 'premium',
            'status' => 'active',
        ]);

        $firstPackage = SubscriptionPackage::query()->create([
            'id' => (string) Str::uuid(),
            'package_code' => 'PREMIUM-30',
            'package_name' => 'Premium 30 Hari',
            'duration_days' => 30,
            'session_quota' => 100,
            'print_quota' => 200,
            'price' => 199000,
            'is_active' => true,
        ]);

        $secondPackage = SubscriptionPackage::query()->create([
            'id' => (string) Str::uuid(),
            'package_code' => 'PREMIUM-90',
            'package_name' => 'Premium 90 Hari',
            'duration_days' => 90,
            'session_quota' => 300,
            'print_quota' => 600,
            'price' => 499000,
            'is_active' => true,
        ]);

        $firstSubscription = CustomerSubscription::query()->create([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'package_id' => $firstPackage->id,
            'status' => 'active',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(29),
            'auto_renew' => false,
        ]);

        $this->postJson('/api/editor/customers/6281234567890/subscriptions/upgrade', [
            'package_code' => 'PREMIUM-90',
            'notes' => 'Upgrade plan',
        ])
            ->assertOk()
            ->assertJsonPath('subscription.package_code', 'PREMIUM-90');

        $newSubscription = CustomerSubscription::query()
            ->where('customer_id', $customer->id)
            ->where('package_id', $secondPackage->id)
            ->first();

        $this->assertNotNull($newSubscription);
        $this->assertSame($firstSubscription->id, $newSubscription?->upgraded_from_id);

        $this->assertDatabaseHas('customer_subscriptions', [
            'id' => $firstSubscription->id,
            'status' => 'upgraded',
        ]);

        $this->assertSame(
            1,
            CustomerSubscription::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->count()
        );
    }

    private function createEditorUser(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'code' => 'editor',
            'name' => 'Editor',
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
