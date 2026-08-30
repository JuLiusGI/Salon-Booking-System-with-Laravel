<?php

namespace App\Http\Requests\Admin;

use App\Models\Staff;
use App\Services\Media\ImageStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = $this->route('service');

        return [
            'service_category_id' => [
                'required',
                Rule::exists('service_categories', 'id')->whereNull('deleted_at'),
            ],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('services', 'name')->ignore($service?->id)->withoutTrashed(),
            ],
            'description' => ['nullable', 'string', 'max:2000'],

            // A booked service occupies real diary time, so zero-length or
            // absurdly long durations are rejected rather than stored.
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],

            'is_active' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],

            'image' => [
                'nullable', 'image',
                'mimes:'.implode(',', ImageStorage::MIME_TYPES),
                'max:'.ImageStorage::MAX_KILOBYTES,
                'dimensions:max_width=5000,max_height=5000',
            ],
            'remove_image' => ['sometimes', 'boolean'],

            // Only staff who can actually be booked may be assigned to a
            // service, otherwise availability could offer an impossible pairing.
            'staff_ids' => ['array'],
            'staff_ids.*' => [
                Rule::exists('staff', 'id')->where(function ($query) {
                    $query->where('is_active', true)
                        ->where('is_bookable', true)
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'duration_minutes.min' => 'A service must take at least 5 minutes.',
            'duration_minutes.max' => 'A service may not run longer than 10 hours.',
            'staff_ids.*.exists' => 'One of the selected staff members is not available for booking.',
            'image.mimes' => 'The image must be a JPEG, PNG, or WebP file.',
            'image.max' => 'The image may not be larger than 4 MB.',
        ];
    }

    /**
     * @return list<int>
     */
    public function staffIds(): array
    {
        return array_map('intval', $this->input('staff_ids', []));
    }

    /**
     * Guard against assigning a service to a staff member who has since been
     * deactivated between the form loading and being submitted.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ids = $this->staffIds();

            if ($ids === []) {
                return;
            }

            $bookable = Staff::query()->bookable()->whereKey($ids)->count();

            if ($bookable !== count(array_unique($ids))) {
                $validator->errors()->add('staff_ids', 'One of the selected staff members is no longer bookable.');
            }
        });
    }
}
