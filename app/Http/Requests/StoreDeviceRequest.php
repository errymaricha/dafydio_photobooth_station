<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('device_code')) {
            $this->merge([
                'device_code' => strtoupper(trim((string) $this->input('device_code'))),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'station_id' => ['required', 'uuid', 'exists:stations,id'],
            'device_type' => ['required', 'string', Rule::in(['android', 'minipc_kiosk', 'print_agent'])],
            'device_code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('android_devices', 'device_code'),
            ],
            'device_name' => ['required', 'string', 'max:100'],
            'api_key' => ['required', 'string', 'min:8', 'max:120'],
            'local_ip' => ['nullable', 'ip'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'os_name' => ['nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:30'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['boolean'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }
}
