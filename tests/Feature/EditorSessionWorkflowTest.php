<?php

namespace Tests\Feature;

use App\Models\AndroidDevice;
use App\Models\AssetFile;
use App\Models\EditJobItem;
use App\Models\PhotoSession;
use App\Models\Printer;
use App\Models\RenderedOutput;
use App\Models\Role;
use App\Models\SessionPhoto;
use App\Models\Station;
use App\Models\Template;
use App\Models\TemplateSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorSessionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_editor_can_create_edit_job_for_a_session(): void
    {
        $editor = $this->createEditorUser();
        $session = $this->createSessionWithPhotos(photoCount: 2);
        $template = $this->createTemplate(slotCount: 2, createdBy: $editor);

        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/editor/sessions/{$session->id}/edit-jobs", [
            'template_id' => $template->id,
            'items' => $session->photos
                ->map(fn (SessionPhoto $photo, int $index): array => [
                    'session_photo_id' => $photo->id,
                    'slot_index' => $index + 1,
                ])
                ->all(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('session_id', $session->id)
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('session_status', 'editing');

        $editJobId = $response->json('edit_job_id');

        $this->assertDatabaseHas('edit_jobs', [
            'id' => $editJobId,
            'session_id' => $session->id,
            'editor_id' => $editor->id,
            'template_id' => $template->id,
            'version_no' => 1,
            'status' => 'draft',
        ]);

        $this->assertDatabaseCount('edit_job_items', 2);
        $this->assertDatabaseHas('photo_sessions', [
            'id' => $session->id,
            'status' => 'editing',
            'template_id' => $template->id,
        ]);
    }

    public function test_editor_cannot_create_edit_job_with_photos_from_another_session(): void
    {
        $editor = $this->createEditorUser();
        $session = $this->createSessionWithPhotos(photoCount: 1);
        $otherSession = $this->createSessionWithPhotos(photoCount: 1);
        $template = $this->createTemplate(slotCount: 1, createdBy: $editor);
        $otherPhoto = $otherSession->photos->first();

        self::assertNotNull($otherPhoto);

        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/editor/sessions/{$session->id}/edit-jobs", [
            'template_id' => $template->id,
            'items' => [
                [
                    'session_photo_id' => $otherPhoto->id,
                    'slot_index' => 1,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseCount('edit_jobs', 0);
    }

    public function test_editor_can_render_an_edit_job_and_promote_session_status(): void
    {
        $editor = $this->createEditorUser();
        $session = $this->createSessionWithPhotos(photoCount: 2, storageDisk: 'public');
        $template = $this->createTemplate(slotCount: 2, createdBy: $editor);

        Sanctum::actingAs($editor);

        $createResponse = $this->postJson("/api/editor/sessions/{$session->id}/edit-jobs", [
            'template_id' => $template->id,
            'items' => $session->photos
                ->map(fn (SessionPhoto $photo, int $index): array => [
                    'session_photo_id' => $photo->id,
                    'slot_index' => $index + 1,
                    'crop_json' => [
                        'zoom' => 1.35,
                        'offset_x' => $index === 0 ? -20 : 15,
                        'offset_y' => $index === 0 ? 10 : -12,
                    ],
                    'transform_json' => [
                        'rotation' => $index === 0 ? 90 : 0,
                    ],
                ])
                ->all(),
        ]);

        $createResponse->assertCreated();

        $editJobId = $createResponse->json('edit_job_id');

        $firstEditJobItem = EditJobItem::query()
            ->where('edit_job_id', $editJobId)
            ->where('slot_index', 1)
            ->first();

        self::assertNotNull($firstEditJobItem);
        self::assertSame([
            'zoom' => 1.35,
            'offset_x' => -20,
            'offset_y' => 10,
        ], $firstEditJobItem->crop_json);
        self::assertSame([
            'rotation' => 90,
        ], $firstEditJobItem->transform_json);

        $renderResponse = $this->postJson("/api/editor/edit-jobs/{$editJobId}/render");

        $renderResponse->assertCreated()
            ->assertJsonPath('status', 'ready_print');

        $renderPath = $renderResponse->json('file_path');

        self::assertNotNull($renderPath);
        Storage::disk('public')->assertExists($renderPath);

        $this->assertDatabaseHas('edit_jobs', [
            'id' => $editJobId,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('photo_sessions', [
            'id' => $session->id,
            'status' => 'ready_print',
        ]);

        $this->assertDatabaseHas('rendered_outputs', [
            'edit_job_id' => $editJobId,
            'session_id' => $session->id,
            'is_active' => true,
            'version_no' => 1,
        ]);
    }

    public function test_editor_can_create_print_order_from_rendered_output(): void
    {
        $editor = $this->createEditorUser();
        $session = $this->createSessionWithPhotos(photoCount: 2, storageDisk: 'public');
        $template = $this->createTemplate(slotCount: 2, createdBy: $editor);
        $printer = $this->createPrinter($session->station_id);

        Sanctum::actingAs($editor);

        $createResponse = $this->postJson("/api/editor/sessions/{$session->id}/edit-jobs", [
            'template_id' => $template->id,
            'items' => $session->photos
                ->map(fn (SessionPhoto $photo, int $index): array => [
                    'session_photo_id' => $photo->id,
                    'slot_index' => $index + 1,
                ])
                ->all(),
        ]);

        $editJobId = $createResponse->json('edit_job_id');

        $this->postJson("/api/editor/edit-jobs/{$editJobId}/render")
            ->assertCreated();

        $renderedOutput = RenderedOutput::query()
            ->where('session_id', $session->id)
            ->where('is_active', true)
            ->first();

        self::assertNotNull($renderedOutput);

        $response = $this->postJson("/api/editor/rendered-outputs/{$renderedOutput->id}/print-orders", [
            'printer_id' => $printer->id,
            'copies' => 2,
            'paper_size' => '4R',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('session_status', 'ready_print');

        $this->assertDatabaseHas('print_orders', [
            'id' => $response->json('print_order_id'),
            'session_id' => $session->id,
            'printer_id' => $printer->id,
            'status' => 'created',
            'total_qty' => 2,
        ]);

        $this->assertDatabaseHas('print_order_items', [
            'print_order_id' => $response->json('print_order_id'),
            'rendered_output_id' => $renderedOutput->id,
            'status' => 'created',
            'copies' => 2,
        ]);

        $this->assertDatabaseHas('photo_sessions', [
            'id' => $session->id,
            'status' => 'ready_print',
        ]);
    }

    public function test_editor_can_queue_print_order_and_update_session_status(): void
    {
        $editor = $this->createEditorUser();
        $session = $this->createSessionWithPhotos(photoCount: 2, storageDisk: 'public');
        $template = $this->createTemplate(slotCount: 2, createdBy: $editor);
        $printer = $this->createPrinter($session->station_id);

        Sanctum::actingAs($editor);

        $createResponse = $this->postJson("/api/editor/sessions/{$session->id}/edit-jobs", [
            'template_id' => $template->id,
            'items' => $session->photos
                ->map(fn (SessionPhoto $photo, int $index): array => [
                    'session_photo_id' => $photo->id,
                    'slot_index' => $index + 1,
                ])
                ->all(),
        ]);

        $editJobId = $createResponse->json('edit_job_id');

        $this->postJson("/api/editor/edit-jobs/{$editJobId}/render")
            ->assertCreated();

        $renderedOutput = RenderedOutput::query()
            ->where('session_id', $session->id)
            ->where('is_active', true)
            ->first();

        self::assertNotNull($renderedOutput);

        $orderResponse = $this->postJson("/api/editor/rendered-outputs/{$renderedOutput->id}/print-orders", [
            'copies' => 1,
            'paper_size' => '4R',
        ]);

        $printOrderId = $orderResponse->json('print_order_id');

        $queueResponse = $this->postJson("/api/editor/print-orders/{$printOrderId}/queue", [
            'printer_id' => $printer->id,
            'priority' => 2,
        ]);

        $queueResponse->assertCreated()
            ->assertJsonPath('order_status', 'queued')
            ->assertJsonPath('session_status', 'queued_print');

        $this->assertDatabaseHas('print_queue_jobs', [
            'print_order_id' => $printOrderId,
            'printer_id' => $printer->id,
            'status' => 'pending',
            'priority' => 2,
        ]);

        $this->assertDatabaseHas('print_orders', [
            'id' => $printOrderId,
            'printer_id' => $printer->id,
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('print_order_items', [
            'print_order_id' => $printOrderId,
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('photo_sessions', [
            'id' => $session->id,
            'status' => 'queued_print',
        ]);
    }

    public function test_editor_can_view_session_detail_with_latest_editor_workflow_data(): void
    {
        $editor = $this->createEditorUser();
        $session = $this->createSessionWithPhotos(photoCount: 2, storageDisk: 'public');
        $template = $this->createTemplate(slotCount: 2, createdBy: $editor);
        $printer = $this->createPrinter($session->station_id);
        $session->load('station');

        Sanctum::actingAs($editor);

        $editJobResponse = $this->postJson("/api/editor/sessions/{$session->id}/edit-jobs", [
            'template_id' => $template->id,
            'items' => $session->photos
                ->map(fn (SessionPhoto $photo, int $index): array => [
                    'session_photo_id' => $photo->id,
                    'slot_index' => $index + 1,
                ])
                ->all(),
        ])->assertCreated();

        $this->postJson("/api/editor/edit-jobs/{$editJobResponse->json('edit_job_id')}/render")
            ->assertCreated();

        $renderedOutput = RenderedOutput::query()
            ->where('session_id', $session->id)
            ->where('is_active', true)
            ->first();

        self::assertNotNull($renderedOutput);
        $renderedOutput->load('file');

        $this->postJson("/api/editor/rendered-outputs/{$renderedOutput->id}/print-orders", [
            'printer_id' => $printer->id,
            'copies' => 2,
            'paper_size' => '4R',
        ])->assertCreated();

        $response = $this->getJson("/api/editor/sessions/{$session->id}");

        $response->assertOk()
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('session_code', $session->session_code)
            ->assertJsonPath('device_name', 'Capture Device')
            ->assertJsonPath('station_code', $session->station?->station_code)
            ->assertJsonCount(2, 'photos')
            ->assertJsonPath('photos.0.capture_index', 1)
            ->assertJsonPath('photos.0.url', url('storage/tests/session-' . $session->id . '-1-thumb.png'))
            ->assertJsonPath('latest_edit_job.template.id', $template->id)
            ->assertJsonPath('latest_edit_job.status', 'completed')
            ->assertJsonPath('active_rendered_output.id', $renderedOutput->id)
            ->assertJsonPath('active_rendered_output.file_url', url('storage/' . $renderedOutput->file->file_path))
            ->assertJsonPath('latest_print_order.printer.id', $printer->id)
            ->assertJsonPath('latest_print_order.status', 'created')
            ->assertJsonPath('latest_print_order.total_qty', 2);
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

    protected function createTemplate(int $slotCount, User $createdBy): Template
    {
        $template = Template::create([
            'template_code' => 'TPL-' . Str::upper(Str::random(6)),
            'template_name' => 'Template ' . Str::upper(Str::random(4)),
            'category' => 'photostrip',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
            'created_by' => $createdBy->id,
            'config_json' => [
                'background_color' => '#ffffff',
            ],
        ]);

        for ($slotIndex = 1; $slotIndex <= $slotCount; $slotIndex++) {
            TemplateSlot::create([
                'template_id' => $template->id,
                'slot_index' => $slotIndex,
                'x' => 50,
                'y' => 50 + (($slotIndex - 1) * 840),
                'width' => 1100,
                'height' => 800,
                'rotation' => 0,
                'border_radius' => 0,
            ]);
        }

        return $template->fresh('slots');
    }

    protected function createPrinter(string $stationId): Printer
    {
        return Printer::create([
            'station_id' => $stationId,
            'printer_code' => 'PR-' . Str::upper(Str::random(6)),
            'printer_name' => 'Main Printer',
            'printer_type' => 'inkjet',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.20',
            'port' => 9100,
            'paper_size_default' => '4R',
            'is_default' => true,
            'status' => 'ready',
            'last_seen_at' => now(),
        ]);
    }

    protected function createSessionWithPhotos(int $photoCount, string $storageDisk = 'public'): PhotoSession
    {
        $station = Station::create([
            'station_code' => 'ST-' . Str::upper(Str::random(6)),
            'station_name' => 'Main Station',
            'location_name' => 'Studio',
            'timezone' => 'Asia/Jakarta',
            'status' => 'online',
        ]);

        $device = AndroidDevice::create([
            'station_id' => $station->id,
            'device_code' => 'DV-' . Str::upper(Str::random(6)),
            'device_name' => 'Capture Device',
            'api_key_hash' => hash('sha256', Str::random(40)),
            'status' => 'active',
        ]);

        $session = PhotoSession::create([
            'session_code' => 'SES-' . Str::upper(Str::random(8)),
            'station_id' => $station->id,
            'device_id' => $device->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'total_expected_photos' => $photoCount,
            'captured_count' => $photoCount,
            'status' => 'uploaded',
            'captured_at' => now(),
        ]);

        for ($captureIndex = 1; $captureIndex <= $photoCount; $captureIndex++) {
            $tinyPng = $this->tinyPng();
            $originalPath = "tests/session-{$session->id}-{$captureIndex}.png";
            $thumbnailPath = "tests/session-{$session->id}-{$captureIndex}-thumb.png";

            Storage::disk($storageDisk)->put($originalPath, $tinyPng);
            Storage::disk($storageDisk)->put($thumbnailPath, $tinyPng);

            $originalFile = AssetFile::create([
                'storage_disk' => $storageDisk,
                'file_path' => $originalPath,
                'file_name' => basename($originalPath),
                'file_ext' => 'png',
                'mime_type' => 'image/png',
                'file_size_bytes' => strlen($tinyPng),
                'width' => 10,
                'height' => 10,
                'file_category' => 'original',
                'created_by_type' => 'system',
            ]);

            $thumbnailFile = AssetFile::create([
                'storage_disk' => $storageDisk,
                'file_path' => $thumbnailPath,
                'file_name' => basename($thumbnailPath),
                'file_ext' => 'png',
                'mime_type' => 'image/png',
                'file_size_bytes' => strlen($tinyPng),
                'width' => 10,
                'height' => 10,
                'file_category' => 'thumbnail',
                'created_by_type' => 'system',
            ]);

            SessionPhoto::create([
                'session_id' => $session->id,
                'capture_index' => $captureIndex,
                'original_file_id' => $originalFile->id,
                'thumbnail_file_id' => $thumbnailFile->id,
                'width' => 10,
                'height' => 10,
                'file_size_bytes' => strlen($tinyPng),
                'mime_type' => 'image/png',
                'uploaded_at' => now(),
                'is_selected' => true,
            ]);
        }

        return $session->fresh('photos');
    }

    protected function tinyPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAGElEQVQYlWP8z8Dwn4EIwESMolGF1FMIAD2cAhL1w47oAAAAAElFTkSuQmCC',
            true,
        ) ?: '';
    }
}
