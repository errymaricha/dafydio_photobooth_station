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

class SessionVoucherTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_apply_and_revoke_session_voucher(): void
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
            'station_code' => 'ST-TEST',
            'station_name' => 'Test Station',
            'status' => 'online',
        ]);

        $session = PhotoSession::create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-TEST',
            'station_id' => $station->id,
            'status' => 'uploaded',
            'source_type' => 'android',
            'total_expected_photos' => 2,
            'captured_count' => 2,
        ]);

        $applyResponse = $this->postJson(
            "/api/editor/sessions/{$session->id}/vouchers",
            [
                'voucher_code' => 'VCHR-TEST',
                'voucher_type' => 'skip',
                'notes' => 'Skip reason',
            ],
        );

        $applyResponse->assertCreated();
        $this->assertDatabaseHas('session_vouchers', [
            'session_id' => $session->id,
            'voucher_code' => 'VCHR-TEST',
            'voucher_type' => 'skip',
            'status' => 'applied',
        ]);

        $voucherId = $applyResponse->json('voucher.id');

        $revokeResponse = $this->postJson(
            "/api/editor/sessions/{$session->id}/vouchers/{$voucherId}/revoke",
        );

        $revokeResponse->assertOk();
        $this->assertDatabaseHas('session_vouchers', [
            'id' => $voucherId,
            'status' => 'revoked',
        ]);
    }
}
