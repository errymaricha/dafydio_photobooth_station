<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStationPricingRequest extends FormRequest
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
            'photobooth_price' => ['required', 'numeric', 'min:0'],
            'additional_print_price' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', 'max:10'],
        ];
    }
}
