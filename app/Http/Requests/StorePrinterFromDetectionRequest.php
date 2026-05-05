<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrinterFromDetectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'printer_name' => ['nullable', 'string', 'max:100'],
            'paper_size_default' => ['nullable', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:20'],
        ];
    }
}
