<?php

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 21))->setTime(fake()->numberBetween(9, 16), 0);
        $duration = fake()->randomElement([30, 60, 90]);

        return [
            'reference' => Appointment::generateReference(),
            'qr_token' => Appointment::generateQrToken(),
            'customer_id' => User::factory(),
            'staff_id' => Staff::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($duration),
            'status' => AppointmentStatus::Confirmed,
            'source' => AppointmentSource::Online,
            'total_duration_minutes' => $duration,
            'total_price' => fake()->randomFloat(2, 400, 5000),
        ];
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    public function pending(): static
    {
        return $this->status(AppointmentStatus::Pending);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(4),
        ]);
    }

    public function noShow(): static
    {
        return $this->status(AppointmentStatus::NoShow);
    }

    /**
     * Place the appointment at an exact time, which conflict tests depend on.
     */
    public function at(CarbonInterface $start, int $durationMinutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($durationMinutes),
            'total_duration_minutes' => $durationMinutes,
        ]);
    }

    public function forStaff(Staff $staff): static
    {
        return $this->state(fn (array $attributes) => [
            'staff_id' => $staff->getKey(),
        ]);
    }

    public function forCustomer(User $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->getKey(),
        ]);
    }

    public function past(): static
    {
        $start = now()->subDays(fake()->numberBetween(1, 60))->setTime(10, 0);

        return $this->state(fn (array $attributes) => [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(60),
        ]);
    }
}
