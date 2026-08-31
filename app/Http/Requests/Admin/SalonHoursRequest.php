<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SalonHoursRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.is_closed' => ['required', 'boolean'],

            // Times are only meaningful on a day the salon actually opens.
            'days.*.opens_at' => ['nullable', 'required_if_accepted:days.*.is_open', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('days', []) as $index => $day) {
                if (filter_var($day['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $opens = $day['opens_at'] ?? null;
                $closes = $day['closes_at'] ?? null;

                if (! $opens || ! $closes) {
                    $validator->errors()->add(
                        "days.{$index}.opens_at",
                        'An open day needs both an opening and a closing time.',
                    );

                    continue;
                }

                // The salon does not trade past midnight, so a closing time at or
                // before opening is a mistake rather than an overnight shift.
                if ($closes <= $opens) {
                    $validator->errors()->add(
                        "days.{$index}.closes_at",
                        'Closing time must be after opening time.',
                    );
                }
            }
        });
    }
}
