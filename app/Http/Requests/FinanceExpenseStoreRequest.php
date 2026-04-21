<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FinanceExpenseStoreRequest extends FormRequest
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
            'station_id' => ['nullable', 'uuid', 'exists:stations,id'],
            'category_code' => ['required', 'string', 'max:40'],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount_subtotal' => ['required', 'numeric', 'gt:0'],
            'amount_tax' => ['nullable', 'numeric', 'min:0'],
            'incurred_at' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['required', 'in:cash,bank_transfer,qris,ewallet'],
            'payment_ref' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['nullable', 'string', 'max:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount_tax' => (float) ($this->input('amount_tax') ?: 0),
            'currency_code' => (string) ($this->input('currency_code') ?: 'IDR'),
        ]);
    }
}
