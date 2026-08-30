<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\StaffAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAvailability>
 */
class StaffAvailabilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'starts_at' => '09:00:00',
            'ends_at' => '18:00:00',
            'is_active' => true,
        ];
    }
}
