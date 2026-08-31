<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BookingRuleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Up to two weeks of notice. Beyond that the salon is effectively
            // closed to online booking, which should be done another way.
            'min_advance_minutes' => ['required', 'integer', 'min:0', 'max:20160'],

            // At least one day of horizon, or nothing could ever be booked.
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],

            'cancellation_deadline_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'reschedule_deadline_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:240'],

            // The grid offered times sit on. Anything under five minutes would
            // produce an unusable wall of options.
            'slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:240'],

            'max_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $interval = (int) $this->input('slot_interval_minutes');
            $max = $this->input('max_duration_minutes');

            if ($max !== null && $max !== '' && (int) $max < $interval) {
                $validator->errors()->add(
                    'max_duration_minutes',
                    'The longest allowed appointment cannot be shorter than one slot.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_advance_days.min' => 'Customers need at least one day of booking horizon.',
            'slot_interval_minutes.min' => 'Slots must be at least 5 minutes apart.',
        ];
    }
}
