<?php

namespace App\Http\Requests;

use App\Models\EditJob;
use App\Models\PhotoSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeviceUploadRenderedOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rendered_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'edit_job_id' => ['required', 'uuid', 'exists:edit_jobs,id'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'dpi' => ['nullable', 'integer', 'min:1'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $session = $this->route('session');

                if (! $session instanceof PhotoSession) {
                    return;
                }

                $editJobId = (string) $this->input('edit_job_id', '');

                if ($editJobId === '') {
                    return;
                }

                $belongsToSession = EditJob::query()
                    ->where('id', $editJobId)
                    ->where('session_id', $session->id)
                    ->exists();

                if (! $belongsToSession) {
                    $validator->errors()->add(
                        'edit_job_id',
                        'Edit job must belong to the selected session.'
                    );
                }
            },
        ];
    }
}
