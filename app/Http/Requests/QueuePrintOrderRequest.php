<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QueuePrintOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'printer_id' => ['required', 'uuid', 'exists:printers,id'],
            'priority' => ['nullable', 'integer'],
        ];
    }
}
