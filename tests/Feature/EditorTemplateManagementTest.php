<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Template;
use App\Models\TemplateSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_create_template(): void
    {
        $editor = $this->createEditorUser();

        Sanctum::actingAs($editor);

        $response = $this->postJson('/api/editor/templates', [
            'template_name' => 'Template Baru',
            'category' => 'photostrip',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
        ]);

        $response->assertCreated()
            ->assertJsonPath('template_name', 'Template Baru')
            ->assertJsonPath('canvas_width', 1200)
            ->assertJsonPath('canvas_height', 1800)
            ->assertJsonCount(1, 'slots');

        $this->assertDatabaseHas('templates', [
            'template_name' => 'Template Baru',
            'paper_size' => '4R',
            'status' => 'active',
        ]);

        $this->assertDatabaseCount('template_slots', 1);
    }

    public function test_editor_can_add_and_remove_slot(): void
    {
        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 1, createdBy: $editor);

        Sanctum::actingAs($editor);

        $createResponse = $this->postJson("/api/editor/templates/{$template->id}/slots/create", [
            'x' => 100,
            'y' => 900,
            'width' => 900,
            'height' => 700,
        ]);

        $createResponse->assertCreated()
            ->assertJsonCount(2, 'slots');

        $this->assertDatabaseHas('template_slots', [
            'template_id' => $template->id,
            'slot_index' => 2,
            'x' => 100,
            'y' => 900,
        ]);

        $deleteResponse = $this->deleteJson("/api/editor/templates/{$template->id}/slots/2");

        $deleteResponse->assertOk()
            ->assertJsonCount(1, 'slots');

        $this->assertDatabaseMissing('template_slots', [
            'template_id' => $template->id,
            'slot_index' => 2,
        ]);
    }

    public function test_editor_can_update_and_archive_template(): void
    {
        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 1, createdBy: $editor);

        Sanctum::actingAs($editor);

        $response = $this->patchJson("/api/editor/templates/{$template->id}", [
            'template_name' => 'Template Arsip',
            'status' => 'archived',
        ]);

        $response->assertOk()
            ->assertJsonPath('template_name', 'Template Arsip')
            ->assertJsonPath('status', 'archived')
            ->assertJsonPath('updated_by.id', $editor->id);

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'template_name' => 'Template Arsip',
            'status' => 'archived',
            'updated_by' => $editor->id,
        ]);
    }

    public function test_editor_can_update_template_detail_fields(): void
    {
        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 1, createdBy: $editor);

        Sanctum::actingAs($editor);

        $response = $this->patchJson("/api/editor/templates/{$template->id}", [
            'template_name' => 'Template Detail Baru',
            'template_code' => 'TPL-DETAIL-BARU',
            'category' => 'wedding',
            'paper_size' => '6R',
            'canvas_width' => 1800,
            'canvas_height' => 1200,
        ]);

        $response->assertOk()
            ->assertJsonPath('template_name', 'Template Detail Baru')
            ->assertJsonPath('template_code', 'TPL-DETAIL-BARU')
            ->assertJsonPath('category', 'wedding')
            ->assertJsonPath('paper_size', '6R')
            ->assertJsonPath('canvas_width', 1800)
            ->assertJsonPath('canvas_height', 1200)
            ->assertJsonPath('updated_by.id', $editor->id);

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'template_name' => 'Template Detail Baru',
            'template_code' => 'TPL-DETAIL-BARU',
            'category' => 'wedding',
            'paper_size' => '6R',
            'canvas_width' => 1800,
            'canvas_height' => 1200,
            'updated_by' => $editor->id,
        ]);
    }

    public function test_editor_can_duplicate_template(): void
    {
        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 2, createdBy: $editor);

        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/editor/templates/{$template->id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('template_name', $template->template_name.' Copy')
            ->assertJsonCount(2, 'slots')
            ->assertJsonPath('created_by.id', $editor->id);

        $duplicatedId = $response->json('id');

        $this->assertDatabaseHas('templates', [
            'id' => $duplicatedId,
            'created_by' => $editor->id,
        ]);

        $this->assertDatabaseCount('template_slots', 4);
    }

    public function test_editor_can_delete_template_with_reason(): void
    {
        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 1, createdBy: $editor);

        Sanctum::actingAs($editor);

        $response = $this->deleteJson("/api/editor/templates/{$template->id}", [
            'reason' => 'Template sudah tidak dipakai.',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Template deleted.');

        $this->assertDatabaseMissing('templates', [
            'id' => $template->id,
        ]);

        $auditPayload = DB::table('audit_logs')
            ->where('entity_id', $template->id)
            ->where('action', 'delete')
            ->value('after_json');

        $this->assertNotNull($auditPayload);

        if (is_string($auditPayload)) {
            $auditPayload = json_decode($auditPayload, true);
        }

        $this->assertSame('Template sudah tidak dipakai.', $auditPayload['reason'] ?? null);
    }

    public function test_editor_can_upload_overlay_template(): void
    {
        Storage::fake('public');

        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 1, createdBy: $editor);

        Sanctum::actingAs($editor);

        $response = $this->post("/api/editor/templates/{$template->id}/overlay", [
            'overlay' => UploadedFile::fake()->image('overlay.png', 3750, 5624),
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Overlay uploaded.')
            ->assertJsonPath('id', $template->id)
            ->assertJsonStructure(['overlay_url']);

        $asset = DB::table('asset_files')
            ->where('file_category', 'template_overlay')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($asset);
        Storage::disk('public')->assertExists($asset->file_path);
        $this->assertSame('image/png', $asset->mime_type);

        $overlayBinary = Storage::disk('public')->get($asset->file_path);
        $overlayImageSize = getimagesizefromstring($overlayBinary);

        $this->assertNotFalse($overlayImageSize);
        $this->assertSame(1200, $overlayImageSize[0]);
        $this->assertSame(1800, $overlayImageSize[1]);

        $decodedOverlay = imagecreatefromstring($overlayBinary);
        $this->assertNotFalse($decodedOverlay);
        imagedestroy($decodedOverlay);

        $this->assertDatabaseHas('template_assets', [
            'template_id' => $template->id,
            'file_id' => $asset->id,
            'asset_type' => 'overlay_png',
        ]);
    }

    public function test_editor_can_upload_thumbnail_template_and_use_it_as_preview(): void
    {
        Storage::fake('public');

        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 1, createdBy: $editor);

        Sanctum::actingAs($editor);

        $response = $this->post("/api/editor/templates/{$template->id}/thumbnail", [
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg', 800, 1200),
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Thumbnail uploaded.')
            ->assertJsonPath('id', $template->id)
            ->assertJsonPath('thumbnail_url', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('preview_url', fn ($value) => is_string($value) && $value !== '');

        $asset = DB::table('asset_files')
            ->where('file_category', 'template_thumbnail')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($asset);
        Storage::disk('public')->assertExists($asset->file_path);

        $this->assertDatabaseHas('template_assets', [
            'template_id' => $template->id,
            'file_id' => $asset->id,
            'asset_type' => 'thumbnail_image',
        ]);
    }

    public function test_editor_can_update_dynamic_layers(): void
    {
        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 1, createdBy: $editor);

        Sanctum::actingAs($editor);

        $payload = [
            'config_json' => [
                'dynamic_layers' => [
                    [
                        'id' => 'layer-text-1',
                        'type' => 'text',
                        'label' => 'Event Name',
                        'text' => 'Photobooth Night',
                        'x' => 40,
                        'y' => 60,
                        'font_size' => 32,
                        'color' => '#111827',
                        'align' => 'left',
                        'opacity' => 90,
                    ],
                    [
                        'id' => 'layer-qr-1',
                        'type' => 'qr',
                        'qr_data' => 'https://photobooth.local/session',
                        'x' => 80,
                        'y' => 120,
                        'width' => 180,
                        'height' => 180,
                        'opacity' => 100,
                    ],
                ],
            ],
        ];

        $response = $this->patchJson("/api/editor/templates/{$template->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('config.dynamic_layers.0.type', 'text')
            ->assertJsonPath('config.dynamic_layers.1.type', 'qr');

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
        ]);
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
            'template_code' => 'TPL-'.Str::upper(Str::random(6)),
            'template_name' => 'Template '.Str::upper(Str::random(4)),
            'category' => 'photostrip',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
            'created_by' => $createdBy->id,
            'updated_by' => $createdBy->id,
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
}
