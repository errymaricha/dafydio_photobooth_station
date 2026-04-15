<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TemplateQrPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'string', 'max:500'],
            'size' => ['nullable', 'integer', 'min:60', 'max:600'],
            'padding' => ['nullable', 'integer', 'min:0', 'max:200'],
            'bg_color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
