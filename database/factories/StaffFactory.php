<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->stylist(),
            'title' => fake()->randomElement(['Senior Stylist', 'Stylist', 'Colour Specialist', 'Nail Technician']),
            'bio' => fake()->paragraph(),
            'hired_on' => fake()->dateTimeBetween('-6 years', '-2 months'),
            'is_active' => true,
            'is_bookable' => true,
            'display_order' => 0,
        ];
    }

    /**
     * A staff member who holds a salon account but cannot be booked, such as a
     * receptionist.
     */
    public function notBookable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bookable' => false,
            'user_id' => User::factory()->role(UserRole::Receptionist),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
