<?php

namespace Tests\Feature;

use App\Models\AndroidDevice;
use App\Models\AssetFile;
use App\Models\CustomerCloudAccount;
use App\Models\PhotoSession;
use App\Models\Role;
use App\Models\SessionPhoto;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCloudAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_cloud_password_for_customer(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $this->postJson('/api/editor/customers/cloud-account', [
            'customer_whatsapp' => '0812-3456-7890',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Customer cloud credential saved.')
            ->assertJsonPath('customer_id', '6281234567890')
            ->assertJsonPath('username', '6281234567890');

        $account = CustomerCloudAccount::query()
            ->where('customer_whatsapp', '6281234567890')
            ->first();

        $this->assertNotNull($account);
        $this->assertTrue(Hash::check('Str0ng!Pass', (string) $account?->getAttribute('cloud_password_hash')));
    }

    public function test_history_returns_sessions_and_photo_file_details_for_customer(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $station = Station::create([
            'id' => (string) Str::uuid(),
            'station_code' => 'ST-TEST',
            'station_name' => 'Main Station',
            'status' => 'online',
        ]);

        $device = AndroidDevice::create([
            'id' => (string) Str::uuid(),
            'station_id' => $station->id,
            'device_code' => 'DV-TEST',
            'device_name' => 'Android Booth',
            'api_key_hash' => Hash::make('secret-device-key'),
            'status' => 'active',
        ]);

        $session = PhotoSession::create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-CLOUD-001',
            'station_id' => $station->id,
            'device_id' => $device->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'status' => 'uploaded',
            'payment_status' => 'paid',
            'payment_method' => 'manual',
            'customer_whatsapp' => '6281234567890',
            'captured_count' => 1,
            'completed_at' => now(),
        ]);

        $assetFile = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => 'sessions/6281234567890/photo_1.jpg',
            'file_name' => 'photo_1.jpg',
            'file_ext' => 'jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 102400,
            'checksum_sha256' => 'abc123',
            'width' => 1200,
            'height' => 1800,
            'file_category' => 'session_original',
            'created_by_type' => 'device',
            'created_by_id' => $device->id,
        ]);

        SessionPhoto::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'capture_index' => 1,
            'slot_index' => 1,
            'original_file_id' => $assetFile->id,
            'checksum_sha256' => 'abc123',
            'width' => 1200,
            'height' => 1800,
            'file_size_bytes' => 102400,
            'mime_type' => 'image/jpeg',
            'is_selected' => true,
            'uploaded_at' => now(),
        ]);

        $this->getJson('/api/editor/customers/0812-3456-7890/history')
            ->assertOk()
            ->assertJsonPath('customer.customer_id', '6281234567890')
            ->assertJsonPath('summary.sessions_count', 1)
            ->assertJsonPath('summary.paid_sessions_count', 1)
            ->assertJsonPath('summary.photos_count', 1)
            ->assertJsonPath('sessions.0.session_code', 'SES-CLOUD-001')
            ->assertJsonPath('sessions.0.photos.0.capture_index', 1)
            ->assertJsonPath('sessions.0.photos.0.file.file_name', 'photo_1.jpg');
    }

    private function createAdminUser(): User
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
