<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpgradeCustomerSubscriptionRequest extends FormRequest
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
            'package_code' => ['required', 'string', 'max:50'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
