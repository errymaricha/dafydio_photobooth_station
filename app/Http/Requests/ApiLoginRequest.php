<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authentication payload for API user login.
 */
class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            /** Primary account email used for API login. @example editor@example.com */
            'email' => ['required', 'email'],
            /** Plain password for the account. @example secret-password */
            'password' => ['required', 'string'],
        ];
    }
}
