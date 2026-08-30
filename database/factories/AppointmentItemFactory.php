<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentItem>
 */
class AppointmentItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'service_id' => Service::factory(),
            'service_name' => fake()->words(3, true),
            'service_price' => fake()->randomFloat(2, 250, 4500),
            'service_duration_minutes' => fake()->randomElement([30, 45, 60]),
            'position' => 0,
        ];
    }

    /**
     * Snapshot a real service, mirroring how booking creates items.
     */
    public function forService(Service $service, int $position = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'service_id' => $service->getKey(),
            'service_name' => $service->name,
            'service_price' => $service->price,
            'service_duration_minutes' => $service->duration_minutes,
            'position' => $position,
        ]);
    }
}
