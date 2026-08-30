<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Services\Media\ImageStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $staff = $this->route('staff');
        $userId = $staff?->user_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId)->withoutTrashed(),
            ],
            'phone' => ['nullable', 'string', 'max:32'],

            // Only salon roles are offered here. A staff record for a customer
            // would be meaningless, and admin promotion happens on the users
            // screen where the last-administrator guard lives.
            'role' => [
                'required',
                Rule::in([UserRole::Stylist->value, UserRole::Receptionist->value]),
            ],

            // Set only when creating; an existing member changes their own
            // password, or uses the reset flow.
            'password' => [
                $staff ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],

            'title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'hired_on' => ['nullable', 'date', 'before_or_equal:today'],
            'is_active' => ['required', 'boolean'],
            'is_bookable' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],

            'photo' => [
                'nullable', 'image',
                'mimes:'.implode(',', ImageStorage::MIME_TYPES),
                'max:'.ImageStorage::MAX_KILOBYTES,
                'dimensions:max_width=5000,max_height=5000',
            ],
            'remove_photo' => ['sometimes', 'boolean'],

            'service_ids' => ['array'],
            'service_ids.*' => [
                Rule::exists('services', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Staff members must be a stylist or a receptionist.',
            'photo.mimes' => 'The photo must be a JPEG, PNG, or WebP file.',
            'photo.max' => 'The photo may not be larger than 4 MB.',
        ];
    }

    public function role(): UserRole
    {
        return UserRole::from($this->string('role')->toString());
    }

    /**
     * @return list<int>
     */
    public function serviceIds(): array
    {
        return array_map('intval', $this->input('service_ids', []));
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // A receptionist does not perform services, so allowing them to be
            // booked would put an unbookable person in the stylist picker.
            if ($this->role() === UserRole::Receptionist && $this->boolean('is_bookable')) {
                $validator->errors()->add('is_bookable', 'A receptionist cannot be booked for services.');
            }
        });
    }
}
