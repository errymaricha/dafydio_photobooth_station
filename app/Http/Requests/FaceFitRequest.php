<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FaceFitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'slot_width' => ['required', 'integer', 'min:10'],
            'slot_height' => ['required', 'integer', 'min:10'],
        ];
    }
}
