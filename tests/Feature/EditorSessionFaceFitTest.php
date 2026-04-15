<?php

namespace Tests\Feature;

use App\Models\AndroidDevice;
use App\Models\AssetFile;
use App\Models\PhotoSession;
use App\Models\Role;
use App\Models\SessionPhoto;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorSessionFaceFitTest extends TestCase
{
    use RefreshDatabase;

    public function test_face_fit_endpoint_returns_response(): void
    {
        Storage::fake('public');

        $editor = $this->createEditorUser();
        $station = Station::create([
            'id' => (string) Str::uuid(),
            'station_code' => 'ST-TEST',
            'station_name' => 'Station Test',
            'timezone' => 'Asia/Jakarta',
            'status' => 'active',
        ]);

        $device = AndroidDevice::create([
            'id' => (string) Str::uuid(),
            'station_id' => $station->id,
            'device_code' => 'DEV-TEST',
            'device_name' => 'Device Test',
            'api_key_hash' => hash('sha256', 'secret'),
            'status' => 'active',
        ]);

        $session = PhotoSession::create([
            'id' => (string) Str::uuid(),
            'session_code' => 'S-TEST',
            'station_id' => $station->id,
            'device_id' => $device->id,
            'status' => 'uploaded',
            'captured_count' => 1,
            'total_expected_photos' => 1,
        ]);

        $file = UploadedFile::fake()->image('face.jpg', 300, 300);
        $path = Storage::disk('public')->putFileAs('sessions', $file, 'face.jpg');

        $asset = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => $path,
            'file_name' => 'face.jpg',
            'file_ext' => 'jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => $file->getSize(),
            'width' => 300,
            'height' => 300,
            'file_category' => 'original',
            'created_by_type' => 'system',
            'created_by_id' => null,
        ]);

        $photo = SessionPhoto::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'capture_index' => 1,
            'original_file_id' => $asset->id,
            'width' => 300,
            'height' => 300,
            'is_selected' => true,
        ]);

        Sanctum::actingAs($editor);

        $response = $this->getJson("/api/editor/sessions/{$session->id}/photos/{$photo->id}/face-fit?slot_width=200&slot_height=300");

        $response->assertOk()
            ->assertJsonStructure(['found']);
    }

    protected function createEditorUser(): User
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
