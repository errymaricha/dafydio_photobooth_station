<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVoucherLibraryRequest extends FormRequest
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
        $voucherId = (string) $this->route('voucher')?->id;

        return [
            'voucher_code' => [
                'required',
                'string',
                'max:120',
                Rule::unique('vouchers', 'voucher_code')->ignore($voucherId, 'id'),
            ],
            'voucher_type' => ['required', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive'],
            'valid_from' => ['nullable', 'date_format:d-m-Y'],
            'valid_until' => ['nullable', 'date_format:d-m-Y', 'after_or_equal:valid_from'],
            'max_usage' => ['nullable', 'integer', 'min:1'],
            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'metadata_json' => ['nullable', 'array'],
        ];
    }
}
