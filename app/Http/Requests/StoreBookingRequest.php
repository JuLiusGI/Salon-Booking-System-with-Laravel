<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_ids' => ['required', 'array', 'min:1', 'max:6'],
            'service_ids.*' => [
                'integer',
                Rule::exists('services', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'staff_id' => [
                'required', 'integer',
                Rule::exists('staff', 'id')
                    ->where('is_active', true)
                    ->where('is_bookable', true)
                    ->whereNull('deleted_at'),
            ],

            // Sent back exactly as the slot was offered, so the server never has
            // to reconstruct the intended instant from a local string.
            'starts_at' => ['required', 'date'],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_ids.required' => 'Please choose at least one service.',
            'service_ids.max' => 'Please choose no more than six services for one appointment.',
            'staff_id.required' => 'Please choose a stylist.',
            'staff_id.exists' => 'That stylist is not taking bookings.',
            'starts_at.required' => 'Please choose a time.',
        ];
    }

    /**
     * @return list<int>
     */
    public function serviceIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('service_ids', []))));
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('starts_at')->toString())->utc();
    }
}
