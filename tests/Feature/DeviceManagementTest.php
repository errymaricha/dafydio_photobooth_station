<?php

namespace Tests\Feature;

use App\Models\AndroidDevice;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_visit_devices_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/devices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('devices/Index'));
    }

    public function test_editor_can_list_and_create_device(): void
    {
        $editor = $this->createEditorUser();
        $station = Station::create([
            'id' => (string) Str::uuid(),
            'station_code' => 'STATION-DEVICE',
            'station_name' => 'Device Station',
            'status' => 'online',
        ]);

        AndroidDevice::create([
            'station_id' => $station->id,
            'device_code' => 'PB-DEVICE-01',
            'device_name' => 'Tablet Booth 1',
            'device_type' => 'android',
            'api_key_hash' => Hash::make('secret-device-key'),
            'status' => 'active',
            'last_heartbeat_at' => now(),
        ]);

        Sanctum::actingAs($editor);

        $this->getJson('/api/editor/devices')
            ->assertOk()
            ->assertJsonPath('data.0.device_code', 'PB-DEVICE-01')
            ->assertJsonPath('data.0.device_type', 'android')
            ->assertJsonPath('data.0.station.station_code', 'STATION-DEVICE')
            ->assertJsonPath('stations.0.station_code', 'STATION-DEVICE');

        $response = $this->postJson('/api/editor/devices', [
            'station_id' => $station->id,
            'device_type' => 'minipc_kiosk',
            'device_code' => 'pb-device-02',
            'device_name' => 'Tablet Booth 2',
            'api_key' => 'secret-device-key-2',
            'local_ip' => '192.168.88.25',
            'app_version' => '1.0.0',
            'os_name' => 'Windows',
            'os_version' => '11',
            'capabilities' => [
                'camera' => true,
                'printer' => true,
                'offline_queue' => true,
                'local_render' => false,
            ],
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Device berhasil ditambahkan.')
            ->assertJsonPath('device.device_code', 'PB-DEVICE-02')
            ->assertJsonPath('device.device_type', 'minipc_kiosk')
            ->assertJsonPath('device.os_name', 'Windows')
            ->assertJsonPath('device.capabilities.camera', true)
            ->assertJsonPath('device.capabilities.printer', true)
            ->assertJsonPath('device.api_key', 'secret-device-key-2')
            ->assertJsonPath('device.api_key_revealed_once', true);

        $device = AndroidDevice::query()
            ->where('device_code', 'PB-DEVICE-02')
            ->firstOrFail();

        $this->assertTrue(Hash::check('secret-device-key-2', $device->api_key_hash));
        $this->assertDatabaseHas('android_devices', [
            'id' => $device->id,
            'station_id' => $station->id,
            'device_name' => 'Tablet Booth 2',
            'device_type' => 'minipc_kiosk',
            'local_ip' => '192.168.88.25',
        ]);
    }

    public function test_device_code_must_be_unique(): void
    {
        $editor = $this->createEditorUser();
        $station = Station::create([
            'id' => (string) Str::uuid(),
            'station_code' => 'STATION-DEVICE',
            'station_name' => 'Device Station',
            'status' => 'online',
        ]);

        AndroidDevice::create([
            'station_id' => $station->id,
            'device_code' => 'PB-DEVICE-01',
            'device_name' => 'Tablet Booth 1',
            'device_type' => 'android',
            'api_key_hash' => Hash::make('secret-device-key'),
            'status' => 'active',
        ]);

        Sanctum::actingAs($editor);

        $this->postJson('/api/editor/devices', [
            'station_id' => $station->id,
            'device_type' => 'android',
            'device_code' => 'PB-DEVICE-01',
            'device_name' => 'Tablet Booth Duplicate',
            'api_key' => 'secret-device-key-2',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_code');
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
