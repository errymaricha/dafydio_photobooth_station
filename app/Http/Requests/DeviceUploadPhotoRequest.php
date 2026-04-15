<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Photo upload payload from Android device.
 */
class DeviceUploadPhotoRequest extends FormRequest
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
            /** Captured image file from camera roll. @example capture.jpg */
            'photo' => ['required', 'file', 'image', 'max:10240'],
            /** Sequential capture number within the session. @example 1 */
            'capture_index' => ['required', 'integer', 'min:1'],
            /** Optional template slot index for multi-slot templates. @example 1 */
            'slot_index' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
