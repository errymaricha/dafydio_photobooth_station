<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PrintAgentFailJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'printer_id' => ['nullable', 'uuid', 'exists:printers,id'],
            'error' => ['required', 'string', 'max:5000'],
        ];
    }
}
