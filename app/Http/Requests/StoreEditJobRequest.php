<?php

namespace App\Http\Requests;

use App\Models\PhotoSession;
use App\Models\SessionPhoto;
use App\Models\TemplateSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEditJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['required', 'uuid', 'exists:templates,id'],
            'editor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.session_photo_id' => ['required', 'uuid', 'distinct', 'exists:session_photos,id'],
            'items.*.slot_index' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.crop_json' => ['nullable', 'array'],
            'items.*.transform_json' => ['nullable', 'array'],
            'items.*.filter_json' => ['nullable', 'array'],
            'edit_state_json' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $session = $this->route('session');

                if (!$session instanceof PhotoSession) {
                    return;
                }

                $items = collect($this->input('items', []));

                if ($items->isEmpty()) {
                    return;
                }

                $submittedPhotoIds = $items
                    ->pluck('session_photo_id')
                    ->filter()
                    ->values();

                $validPhotoIds = SessionPhoto::query()
                    ->where('session_id', $session->id)
                    ->whereIn('id', $submittedPhotoIds)
                    ->pluck('id');

                if ($submittedPhotoIds->diff($validPhotoIds)->isNotEmpty()) {
                    $validator->errors()->add('items', 'Selected photos must belong to this session.');
                }

                if ($this->filled('editor_id') && $this->user()?->id !== $this->input('editor_id')) {
                    $validator->errors()->add('editor_id', 'Editor must match the authenticated user.');
                }

                $templateId = $this->input('template_id');

                if (!$templateId) {
                    return;
                }

                $submittedSlots = $items
                    ->pluck('slot_index')
                    ->filter(fn (mixed $slotIndex): bool => is_numeric($slotIndex))
                    ->map(fn (mixed $slotIndex): int => (int) $slotIndex)
                    ->values();

                $validSlots = TemplateSlot::query()
                    ->where('template_id', $templateId)
                    ->whereIn('slot_index', $submittedSlots)
                    ->pluck('slot_index')
                    ->map(fn (mixed $slotIndex): int => (int) $slotIndex);

                if ($submittedSlots->diff($validSlots)->isNotEmpty()) {
                    $validator->errors()->add('items', 'Selected slots must exist on the chosen template.');
                }
            },
        ];
    }
}
