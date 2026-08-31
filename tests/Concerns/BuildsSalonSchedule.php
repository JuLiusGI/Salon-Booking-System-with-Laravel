<?php

namespace Tests\Concerns;

use App\Enums\ScheduleExceptionType;
use App\Models\BookingRule;
use App\Models\SalonHour;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\StaffAvailability;
use App\Services\Availability\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds a predictable salon so availability tests read as scenarios rather than
 * as fixture setup.
 *
 * Times passed to these helpers are always salon wall-clock, because that is how
 * the salon states its own rules. Conversion to UTC is the engine's job, and
 * getting it wrong is exactly what these tests are for.
 */
trait BuildsSalonSchedule
{
    protected function salonTimezone(): string
    {
        return config('salon.timezone');
    }

    /**
     * A salon-local instant, returned in UTC.
     */
    protected function local(string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, $this->salonTimezone())->utc();
    }

    /**
     * A salon-local date, for passing to the engine.
     */
    protected function localDate(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->salonTimezone())->startOfDay();
    }

    /**
     * Open every day at the given wall-clock hours.
     */
    protected function openSalon(string $opensAt = '09:00', string $closesAt = '17:00'): void
    {
        foreach (range(0, 6) as $day) {
            SalonHour::updateOrCreate(
                ['day_of_week' => $day],
                ['opens_at' => $opensAt, 'closes_at' => $closesAt, 'is_closed' => false],
            );
        }
    }

    protected function closeSalonOn(int $dayOfWeek): void
    {
        SalonHour::updateOrCreate(
            ['day_of_week' => $dayOfWeek],
            ['opens_at' => null, 'closes_at' => null, 'is_closed' => true],
        );
    }

    /**
     * A bookable stylist rostered every day at the given wall-clock hours.
     */
    protected function rosteredStylist(string $startsAt = '09:00', string $endsAt = '17:00'): Staff
    {
        $staff = Staff::factory()->create(['display_order' => 0]);

        foreach (range(0, 6) as $day) {
            StaffAvailability::create([
                'staff_id' => $staff->id,
                'day_of_week' => $day,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_active' => true,
            ]);
        }

        return $staff;
    }

    /**
     * A service the stylist is qualified to perform.
     */
    protected function serviceFor(Staff $staff, int $durationMinutes = 60, float $price = 1000): Service
    {
        $service = Service::factory()->create([
            'service_category_id' => ServiceCategory::factory()->create()->id,
            'duration_minutes' => $durationMinutes,
            'price' => $price,
            'is_active' => true,
        ]);

        $service->staff()->attach($staff);

        return $service;
    }

    /**
     * @param  array<string, int|null>  $overrides
     */
    protected function bookingRules(array $overrides = []): BookingRule
    {
        BookingRule::query()->delete();

        return BookingRule::create(array_merge([
            'min_advance_minutes' => 60,
            'max_advance_days' => 60,
            'cancellation_deadline_hours' => 24,
            'reschedule_deadline_hours' => 24,
            'buffer_minutes' => 0,
            'slot_interval_minutes' => 15,
            'max_duration_minutes' => null,
        ], $overrides));
    }

    /**
     * Block time for one staff member, in salon wall-clock terms.
     */
    protected function blockStaff(
        Staff $staff,
        string $from,
        string $to,
        ScheduleExceptionType $type = ScheduleExceptionType::Break,
    ): ScheduleException {
        return ScheduleException::create([
            'staff_id' => $staff->id,
            'type' => $type,
            'starts_at' => $this->local($from),
            'ends_at' => $this->local($to),
            'reason' => $type->label(),
        ]);
    }

    /**
     * Close the whole salon, in salon wall-clock terms.
     */
    protected function closeSalonBetween(
        string $from,
        string $to,
        ScheduleExceptionType $type = ScheduleExceptionType::Holiday,
    ): ScheduleException {
        return ScheduleException::create([
            'staff_id' => null,
            'type' => $type,
            'starts_at' => $this->local($from),
            'ends_at' => $this->local($to),
            'reason' => $type->label(),
        ]);
    }

    /**
     * Replace the salon's hours for one date.
     */
    protected function specialHoursOn(string $date, string $opensAt, string $closesAt): ScheduleException
    {
        return ScheduleException::create([
            'staff_id' => null,
            'type' => ScheduleExceptionType::SpecialHours,
            'starts_at' => $this->local("{$date} 00:00"),
            'ends_at' => $this->local("{$date} 23:59"),
            'override_opens_at' => $opensAt,
            'override_closes_at' => $closesAt,
            'reason' => 'Special hours',
        ]);
    }

    /**
     * The wall-clock start times the engine offered, for readable assertions.
     *
     * @param  Collection<int, Slot>  $slots
     * @return list<string>
     */
    protected function labels($slots): array
    {
        return $slots
            ->map(fn ($slot) => $slot->startsAt->setTimezone($this->salonTimezone())->format('H:i'))
            ->all();
    }
}
