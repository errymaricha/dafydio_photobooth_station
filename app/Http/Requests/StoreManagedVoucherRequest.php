<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreManagedVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'uuid', 'exists:photo_sessions,id'],
            'voucher_code' => ['required', 'string', 'max:120'],
            'voucher_type' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'metadata_json' => ['nullable', 'array'],
        ];
    }
}
