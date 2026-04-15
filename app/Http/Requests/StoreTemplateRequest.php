<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'template_name' => ['required', 'string', 'max:100'],
            'template_code' => ['nullable', 'string', 'max:50', 'unique:templates,template_code'],
            'category' => ['nullable', 'string', 'max:50'],
            'paper_size' => ['nullable', 'string', 'max:30'],
            'canvas_width' => ['required', 'integer', 'min:1'],
            'canvas_height' => ['required', 'integer', 'min:1'],
            'preview_url' => ['nullable', 'string'],
            'config_json' => ['nullable', 'array'],
        ];
    }
}
