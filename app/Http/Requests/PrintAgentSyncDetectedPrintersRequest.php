<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrintAgentSyncDetectedPrintersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'station_id' => ['required', 'uuid', 'exists:stations,id'],
            'printers' => ['required', 'array', 'min:1'],
            'printers.*.os_identifier' => ['required', 'string', 'max:191'],
            'printers.*.printer_name' => ['required', 'string', 'max:120'],
            'printers.*.printer_type' => ['nullable', 'string', 'max:30'],
            'printers.*.connection_type' => ['nullable', 'string', 'max:20'],
            'printers.*.ip_address' => ['nullable', 'string', 'max:64'],
            'printers.*.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'printers.*.driver_name' => ['nullable', 'string', 'max:100'],
            'printers.*.paper_size_default' => ['nullable', 'string', 'max:30'],
            'printers.*.status' => ['nullable', 'string', 'max:20'],
            'printers.*.is_default' => ['nullable', 'boolean'],
            'printers.*.capabilities' => ['nullable', 'array'],
        ];
    }
}
