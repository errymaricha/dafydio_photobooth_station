<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PrintAgentHeartbeatRequest extends FormRequest
{
 public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:ready,printing,offline,error,paused'],
            'last_error' => ['nullable', 'string'],
            'ip_address' => ['nullable', 'string', 'max:64'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'meta' => ['nullable', 'array'],
        ];
    }
}