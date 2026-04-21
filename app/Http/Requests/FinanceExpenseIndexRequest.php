<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FinanceExpenseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'station_id' => ['nullable', 'uuid', 'exists:stations,id'],
            'category_code' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $dateFrom = $this->date('date_from');
                $dateTo = $this->date('date_to');

                if (! $dateFrom || ! $dateTo) {
                    return;
                }

                if ($dateFrom->diffInDays($dateTo) > 366) {
                    $validator->errors()->add(
                        'date_to',
                        'Maximum report range is 366 days.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_from' => (string) ($this->input('date_from') ?: now()->subDays(29)->toDateString()),
            'date_to' => (string) ($this->input('date_to') ?: now()->toDateString()),
            'per_page' => (int) ($this->input('per_page') ?: 20),
        ]);
    }
}
