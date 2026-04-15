<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Device authentication payload.
 */
class DeviceLoginRequest extends FormRequest
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
            /** Device code registered in station setup. @example DV-AB12CD */
            'device_code' => ['required', 'string', 'max:255'],
            /** Secret API key paired to the device. @example top-secret-device-key */
            'api_key' => ['required', 'string', 'max:255'],
        ];
    }
}
