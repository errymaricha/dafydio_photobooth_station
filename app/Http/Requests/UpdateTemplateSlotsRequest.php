<?php

namespace App\Http\Requests;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTemplateSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.slot_index' => ['required', 'integer', 'min:1', 'distinct'],
            'slots.*.x' => ['required', 'integer', 'min:0'],
            'slots.*.y' => ['required', 'integer', 'min:0'],
            'slots.*.width' => ['required', 'integer', 'min:1'],
            'slots.*.height' => ['required', 'integer', 'min:1'],
            'slots.*.rotation' => ['nullable', 'numeric', 'between:-360,360'],
            'slots.*.border_radius' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $template = $this->route('template');

                if (!$template instanceof Template) {
                    return;
                }

                $slots = collect($this->input('slots', []));

                if ($slots->isEmpty()) {
                    return;
                }

                $submittedSlotIndexes = $slots
                    ->pluck('slot_index')
                    ->filter(fn (mixed $slotIndex): bool => is_numeric($slotIndex))
                    ->map(fn (mixed $slotIndex): int => (int) $slotIndex)
                    ->values();

                $validSlotIndexes = $template->slots()
                    ->whereIn('slot_index', $submittedSlotIndexes)
                    ->pluck('slot_index')
                    ->map(fn (mixed $slotIndex): int => (int) $slotIndex);

                if ($submittedSlotIndexes->diff($validSlotIndexes)->isNotEmpty()) {
                    $validator->errors()->add('slots', 'Selected slots must exist on the template.');
                }

                $canvasWidth = (int) ($template->canvas_width ?? 0);
                $canvasHeight = (int) ($template->canvas_height ?? 0);

                if ($canvasWidth <= 0 || $canvasHeight <= 0) {
                    return;
                }

                $slots->each(function (mixed $slotData, int $index) use ($canvasHeight, $canvasWidth, $validator): void {
                    if (!is_array($slotData)) {
                        return;
                    }

                    $x = (int) ($slotData['x'] ?? 0);
                    $y = (int) ($slotData['y'] ?? 0);
                    $width = (int) ($slotData['width'] ?? 0);
                    $height = (int) ($slotData['height'] ?? 0);

                    if ($x + $width > $canvasWidth || $y + $height > $canvasHeight) {
                        $validator->errors()->add(
                            "slots.{$index}",
                            'Slot must stay within canvas bounds.',
                        );
                    }
                });
            },
        ];
    }
}
