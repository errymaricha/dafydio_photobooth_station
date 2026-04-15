<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Template;
use App\Models\TemplateSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorTemplateLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_update_template_slot_layout(): void
    {
        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 2, createdBy: $editor);

        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/editor/templates/{$template->id}/slots", [
            'slots' => [
                [
                    'slot_index' => 1,
                    'x' => 80,
                    'y' => 120,
                    'width' => 960,
                    'height' => 700,
                    'rotation' => 2.5,
                    'border_radius' => 22,
                ],
                [
                    'slot_index' => 2,
                    'x' => 90,
                    'y' => 980,
                    'width' => 940,
                    'height' => 680,
                    'rotation' => 0,
                    'border_radius' => 18,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('id', $template->id)
            ->assertJsonPath('slots.0.slot_index', 1)
            ->assertJsonPath('slots.0.x', 80)
            ->assertJsonPath('slots.0.y', 120)
            ->assertJsonPath('slots.0.width', 960)
            ->assertJsonPath('slots.0.height', 700)
            ->assertJsonPath('slots.0.rotation', '2.50')
            ->assertJsonPath('slots.0.border_radius', 22);

        $this->assertDatabaseHas('template_slots', [
            'template_id' => $template->id,
            'slot_index' => 1,
            'x' => 80,
            'y' => 120,
            'width' => 960,
            'height' => 700,
            'border_radius' => 22,
        ]);
    }

    public function test_editor_cannot_update_slots_outside_canvas_bounds(): void
    {
        $editor = $this->createEditorUser();
        $template = $this->createTemplate(slotCount: 1, createdBy: $editor);

        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/editor/templates/{$template->id}/slots", [
            'slots' => [
                [
                    'slot_index' => 1,
                    'x' => 500,
                    'y' => 1400,
                    'width' => 900,
                    'height' => 700,
                    'rotation' => 0,
                    'border_radius' => 0,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slots.0']);
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
}
