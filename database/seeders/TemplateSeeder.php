<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Template;
use App\Models\TemplateSlot;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = Template::firstOrNew([
            'template_code' => 'TPL-2SLOT',
        ]);

        if (! $template->exists) {
            $template->id = (string) Str::uuid();
        }

        $template->fill([
            'template_name' => '2 Photo Vertical',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'config_json' => [
                'background_color' => '#ffffff',
            ],
            'status' => 'active',
        ]);
        $template->save();

        $this->upsertSlot($template->id, 1, 0, 0, 1200, 900);
        $this->upsertSlot($template->id, 2, 0, 900, 1200, 900);
    }

    private function upsertSlot(
        string $templateId,
        int $slotIndex,
        int $x,
        int $y,
        int $width,
        int $height,
    ): void {
        $slot = TemplateSlot::firstOrNew([
            'template_id' => $templateId,
            'slot_index' => $slotIndex,
        ]);

        if (! $slot->exists) {
            $slot->id = (string) Str::uuid();
        }

        $slot->fill([
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'rotation' => 0,
            'border_radius' => 0,
        ]);
        $slot->save();
    }
}
