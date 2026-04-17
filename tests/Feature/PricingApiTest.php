<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_fetch_and_update_station_pricing(): void
    {
        $editor = $this->createEditorUser();
        $station = Station::create([
            'id' => (string) Str::uuid(),
            'station_code' => 'ST-MAIN',
            'station_name' => 'Main Station',
            'status' => 'online',
            'photobooth_price' => 35000,
            'additional_print_price' => 5000,
            'currency_code' => 'IDR',
        ]);

        Sanctum::actingAs($editor);

        $this->getJson('/api/editor/pricing')
            ->assertOk()
            ->assertJsonPath('station.id', $station->id)
            ->assertJsonPath('pricing.photobooth_price', fn ($value) => (float) $value === 35000.0)
            ->assertJsonPath('pricing.additional_print_price', fn ($value) => (float) $value === 5000.0)
            ->assertJsonPath('pricing.currency_code', 'IDR');

        $this->patchJson('/api/editor/pricing', [
            'photobooth_price' => 55000,
            'additional_print_price' => 10000,
            'currency_code' => 'idr',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Pricing updated.')
            ->assertJsonPath('pricing.photobooth_price', fn ($value) => (float) $value === 55000.0)
            ->assertJsonPath('pricing.additional_print_price', fn ($value) => (float) $value === 10000.0)
            ->assertJsonPath('pricing.currency_code', 'IDR');

        $this->assertDatabaseHas('stations', [
            'id' => $station->id,
            'photobooth_price' => 55000,
            'additional_print_price' => 10000,
            'currency_code' => 'IDR',
        ]);
    }

    private function createEditorUser(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'code' => 'admin',
            'name' => 'Administrator',
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
