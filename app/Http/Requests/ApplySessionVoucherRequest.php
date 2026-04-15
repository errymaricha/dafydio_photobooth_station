<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplySessionVoucherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'voucher_code' => ['required', 'string', 'max:120'],
            'voucher_type' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'metadata_json' => ['nullable', 'array'],
        ];
    }
}
