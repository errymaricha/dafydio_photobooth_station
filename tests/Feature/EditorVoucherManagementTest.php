<?php

namespace Tests\Feature;

use App\Models\PhotoSession;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorVoucherManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_store_list_and_revoke_voucher_from_management_api(): void
    {
        $user = User::factory()->create();
        $role = Role::create([
            'id' => (string) Str::uuid(),
            'code' => 'admin',
            'name' => 'Admin',
        ]);
        $user->roles()->attach($role->id);

        Sanctum::actingAs($user);

        $station = Station::create([
            'id' => (string) Str::uuid(),
            'station_code' => 'ST-VM-01',
            'station_name' => 'Voucher Station',
            'status' => 'online',
        ]);

        $session = PhotoSession::create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-VM-01',
            'station_id' => $station->id,
            'status' => 'uploaded',
            'source_type' => 'android',
            'total_expected_photos' => 2,
            'captured_count' => 2,
        ]);

        $storeResponse = $this->postJson('/api/editor/vouchers', [
            'session_id' => $session->id,
            'voucher_code' => 'VOUCHER-MGMT-001',
            'voucher_type' => 'promo',
            'notes' => 'Applied from management screen',
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('voucher.session_id', $session->id)
            ->assertJsonPath('voucher.voucher_code', 'VOUCHER-MGMT-001')
            ->assertJsonPath('voucher.status', 'applied');

        $this->assertDatabaseHas('session_events', [
            'session_id' => $session->id,
            'event_type' => 'voucher_applied',
        ]);

        $listResponse = $this->getJson('/api/editor/vouchers?status=applied&search=VOUCHER-MGMT-001');

        $listResponse->assertOk()
            ->assertJsonPath('data.0.voucher_code', 'VOUCHER-MGMT-001')
            ->assertJsonPath('data.0.session_id', $session->id);

        $voucherId = (string) $storeResponse->json('voucher.id');

        $revokeResponse = $this->postJson("/api/editor/vouchers/{$voucherId}/revoke");

        $revokeResponse->assertOk()
            ->assertJsonPath('voucher.status', 'revoked');

        $this->assertDatabaseHas('session_events', [
            'session_id' => $session->id,
            'event_type' => 'voucher_revoked',
        ]);
    }

    public function test_admin_can_create_list_and_deactivate_master_voucher(): void
    {
        $user = User::factory()->create();
        $role = Role::create([
            'id' => (string) Str::uuid(),
            'code' => 'admin',
            'name' => 'Admin',
        ]);
        $user->roles()->attach($role->id);

        Sanctum::actingAs($user);

        $storeResponse = $this->postJson('/api/editor/voucher-library', [
            'voucher_code' => 'MASTER-VCH-001',
            'voucher_type' => 'skip',
            'valid_from' => '15-04-2026',
            'valid_until' => '20-04-2026',
            'max_usage' => 10,
            'discount_type' => 'percent',
            'discount_value' => 20,
            'notes' => 'Master voucher for before payment',
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('voucher.voucher_code', 'MASTER-VCH-001')
            ->assertJsonPath('voucher.status', 'active')
            ->assertJsonPath('voucher.valid_from', '15-04-2026')
            ->assertJsonPath('voucher.valid_until', '20-04-2026')
            ->assertJsonPath('voucher.discount_type', 'percent');

        $listResponse = $this->getJson('/api/editor/voucher-library?status=active&search=MASTER-VCH-001');
        $listResponse->assertOk()
            ->assertJsonPath('data.0.voucher_code', 'MASTER-VCH-001')
            ->assertJsonPath('data.0.status', 'active');

        $voucherId = (string) $storeResponse->json('voucher.id');

        $updateResponse = $this->patchJson("/api/editor/voucher-library/{$voucherId}", [
            'voucher_code' => 'MASTER-VCH-001-EDIT',
            'voucher_type' => 'free',
            'status' => 'active',
            'valid_from' => '16-04-2026',
            'valid_until' => '21-04-2026',
            'max_usage' => 12,
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'notes' => 'Updated master voucher',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('voucher.voucher_code', 'MASTER-VCH-001-EDIT')
            ->assertJsonPath('voucher.voucher_type', 'free')
            ->assertJsonPath('voucher.valid_from', '16-04-2026')
            ->assertJsonPath('voucher.valid_until', '21-04-2026')
            ->assertJsonPath('voucher.discount_type', 'fixed');

        $deactivateResponse = $this->postJson("/api/editor/voucher-library/{$voucherId}/deactivate");

        $deactivateResponse->assertOk()
            ->assertJsonPath('voucher.status', 'inactive');
    }

    public function test_admin_can_generate_voucher_quote_from_library(): void
    {
        $user = User::factory()->create();
        $role = Role::create([
            'id' => (string) Str::uuid(),
            'code' => 'admin',
            'name' => 'Admin',
        ]);
        $user->roles()->attach($role->id);

        Sanctum::actingAs($user);

        $this->postJson('/api/editor/voucher-library', [
            'voucher_code' => 'QUOTE-PROMO-15',
            'voucher_type' => 'promo',
            'discount_type' => 'percent',
            'discount_value' => 15,
            'min_purchase_amount' => 50000,
        ])->assertCreated();

        $this->postJson('/api/editor/voucher-library/quote', [
            'subtotal_amount' => 100000,
            'voucher_code' => 'QUOTE-PROMO-15',
        ])
            ->assertOk()
            ->assertJsonPath('quote.subtotal_amount', fn ($value) => (float) $value === 100000.0)
            ->assertJsonPath('quote.discount_amount', fn ($value) => (float) $value === 15000.0)
            ->assertJsonPath('quote.total_due', fn ($value) => (float) $value === 85000.0)
            ->assertJsonPath('quote.payment_required', true);
    }
}
