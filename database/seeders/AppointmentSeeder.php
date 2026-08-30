<?php

namespace Database\Seeders;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AppointmentSeeder extends Seeder
{
    /**
     * Fixed slot starts, spaced two hours apart. Seeded appointments are capped
     * below two hours so that generated demo data never double-books a staff
     * member, which would contradict the booking rules the app enforces.
     *
     * @var list<int>
     */
    private const SLOT_HOURS = [9, 11, 14, 16];

    private const MAX_SEEDED_DURATION = 115;

    public function run(): void
    {
        $staffMembers = Staff::query()->bookable()->with('services')->get();
        $customers = User::query()->role(UserRole::Customer)->get();

        if ($staffMembers->isEmpty() || $customers->isEmpty()) {
            return;
        }

        foreach ($this->workingDays() as $day) {
            foreach ($staffMembers as $staff) {
                $services = $staff->services->where('is_active', true);

                if ($services->isEmpty()) {
                    continue;
                }

                foreach (self::SLOT_HOURS as $hour) {
                    // Roughly half the slots stay free so the calendar and the
                    // availability engine both have gaps to work with.
                    if (fake()->boolean(45) === false) {
                        continue;
                    }

                    $this->createAppointment(
                        $day->copy()->setTime($hour, 0),
                        $staff,
                        $customers->random(),
                        $services,
                    );
                }
            }
        }
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function workingDays(): Collection
    {
        return collect(range(-30, 21))
            ->map(fn (int $offset) => now()->addDays($offset)->startOfDay())
            // The salon is closed on Sundays, matching SalonHoursSeeder.
            ->reject(fn (Carbon $date) => $date->dayOfWeek === Carbon::SUNDAY)
            ->values();
    }

    /**
     * @param  Collection<int, Service>  $services
     */
    private function createAppointment(Carbon $start, Staff $staff, User $customer, $services): void
    {
        // One or two services, kept within the slot length.
        $chosen = $services->random(min(fake()->numberBetween(1, 2), $services->count()));
        $chosen = $chosen instanceof Service ? collect([$chosen]) : $chosen;

        $duration = (int) $chosen->sum('duration_minutes');

        if ($duration > self::MAX_SEEDED_DURATION) {
            $chosen = $chosen->take(1);
            $duration = (int) $chosen->sum('duration_minutes');
        }

        if ($duration === 0 || $duration > self::MAX_SEEDED_DURATION) {
            return;
        }

        $isPast = $start->isPast();

        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($duration),
            'status' => $this->statusFor($isPast),
            'source' => fake()->randomElement(AppointmentSource::cases()),
            'total_duration_minutes' => $duration,
            'total_price' => $chosen->sum('price'),
            'notes' => fake()->optional(0.3)->sentence(6),
        ]);

        if ($appointment->status === AppointmentStatus::Completed) {
            $appointment->forceFill(['completed_at' => $appointment->ends_at])->save();
        }

        if ($appointment->status === AppointmentStatus::Cancelled) {
            $appointment->forceFill([
                'cancelled_at' => $start->copy()->subDay(),
                'cancellation_reason' => 'Customer rescheduled by phone',
            ])->save();
        }

        foreach ($chosen->values() as $position => $service) {
            AppointmentItem::factory()->forService($service, $position)->create([
                'appointment_id' => $appointment->id,
            ]);
        }
    }

    private function statusFor(bool $isPast): AppointmentStatus
    {
        return $isPast
            ? fake()->randomElement([
                AppointmentStatus::Completed,
                AppointmentStatus::Completed,
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ])
            : fake()->randomElement([
                AppointmentStatus::Confirmed,
                AppointmentStatus::Confirmed,
                AppointmentStatus::Pending,
            ]);
    }
}
