<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'template_name' => ['sometimes', 'string', 'max:100'],
            'template_code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('templates', 'template_code')->ignore($this->route('template')),
            ],
            'category' => ['sometimes', 'nullable', 'string', 'max:50'],
            'paper_size' => ['sometimes', 'nullable', 'string', 'max:30'],
            'canvas_width' => ['sometimes', 'integer', 'min:1'],
            'canvas_height' => ['sometimes', 'integer', 'min:1'],
            'preview_url' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:active,archived'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config_json' => ['sometimes', 'nullable', 'array'],
            'config_json.dynamic_layers' => ['sometimes', 'array'],
            'config_json.dynamic_layers.*.id' => ['nullable', 'string', 'max:50'],
            'config_json.dynamic_layers.*.type' => ['required_with:config_json.dynamic_layers', 'string', 'in:text,qr'],
            'config_json.dynamic_layers.*.label' => ['nullable', 'string', 'max:100'],
            'config_json.dynamic_layers.*.text' => ['nullable', 'string', 'max:200'],
            'config_json.dynamic_layers.*.qr_data' => ['nullable', 'string', 'max:500'],
            'config_json.dynamic_layers.*.x' => ['nullable', 'integer', 'min:0'],
            'config_json.dynamic_layers.*.y' => ['nullable', 'integer', 'min:0'],
            'config_json.dynamic_layers.*.width' => ['nullable', 'integer', 'min:10'],
            'config_json.dynamic_layers.*.height' => ['nullable', 'integer', 'min:10'],
            'config_json.dynamic_layers.*.font_size' => ['nullable', 'integer', 'min:8', 'max:200'],
            'config_json.dynamic_layers.*.color' => ['nullable', 'string', 'max:20'],
            'config_json.dynamic_layers.*.align' => ['nullable', 'string', 'in:left,center,right'],
            'config_json.dynamic_layers.*.opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'config_json.dynamic_layers.*.enabled' => ['nullable', 'boolean'],
            'config_json.dynamic_layers.*.padding' => ['nullable', 'integer', 'min:0', 'max:200'],
            'config_json.dynamic_layers.*.bg_color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
