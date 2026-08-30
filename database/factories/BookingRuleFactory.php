<?php

namespace Database\Factories;

use App\Models\BookingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRule>
 */
class BookingRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return config('salon.booking_rule_defaults');
    }
}
