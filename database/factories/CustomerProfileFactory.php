<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'birthday' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'gender' => fake()->randomElement(Gender::cases()),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'notes' => null,
            'preferences' => fake()->optional(0.4)->sentence(),
            'allergies' => fake()->optional(0.2)->words(2, true),
            'service_notes' => null,
        ];
    }
}
