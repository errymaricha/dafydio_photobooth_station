<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrintAgentQueueActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'printer_id' => ['nullable', 'uuid', 'exists:printers,id'],
        ];
    }
}
