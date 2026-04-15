<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePrintOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'printer_id' => ['nullable', 'uuid', 'exists:printers,id'],
            'copies' => ['nullable', 'integer', 'min:1'],
            'paper_size' => ['nullable', 'string', 'max:30'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
