<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeviceStartSessionRequest extends FormRequest
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
            'voucher_code' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['nullable', 'string', 'in:manual,qris,cash'],
            'customer_whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9]{8,16}$/'],
            'additional_print_count' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }
}
