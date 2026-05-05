<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeviceTemplateIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** Filter by template category. */
            'category' => ['nullable', 'string', 'max:100'],
            /** Filter by paper size. */
            'paper_size' => ['nullable', 'string', 'max:20'],
            /** Search by template code or template name. */
            'q' => ['nullable', 'string', 'max:120'],
            /** Return templates updated from this timestamp/date. */
            'updated_since' => ['nullable', 'date'],
            /** Maximum number of templates returned in one response. */
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            /** Include template slots in response payload. */
            'include_slots' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $allowed = ['1', '0', 'true', 'false'];
                    $normalized = strtolower((string) $value);

                    if (! in_array($normalized, $allowed, true)) {
                        $fail('The '.$attribute.' field must be true or false.');
                    }
                },
            ],
        ];
    }
}
