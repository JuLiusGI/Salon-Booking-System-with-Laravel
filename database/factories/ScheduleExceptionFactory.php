<?php

namespace Database\Factories;

use App\Enums\ScheduleExceptionType;
use App\Models\ScheduleException;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleException>
 */
class ScheduleExceptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+30 days');
        $end = (clone $start)->modify('+2 hours');

        return [
            'staff_id' => Staff::factory(),
            'type' => ScheduleExceptionType::Break,
            'starts_at' => $start,
            'ends_at' => $end,
            'reason' => fake()->optional()->sentence(3),
        ];
    }

    /**
     * A salon-wide exception, which has no staff member attached.
     */
    public function salonWide(ScheduleExceptionType $type = ScheduleExceptionType::Holiday): static
    {
        return $this->state(fn (array $attributes) => [
            'staff_id' => null,
            'type' => $type,
        ]);
    }

    public function type(ScheduleExceptionType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
