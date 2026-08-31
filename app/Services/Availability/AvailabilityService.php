<?php

namespace App\Services\Availability;

use App\Models\Service;
use App\Models\Staff;
use App\Services\Booking\BookingRuleChecker;
use App\Services\Scheduling\SalonHoursResolver;
use App\Services\Scheduling\StaffScheduleResolver;
use App\Services\Scheduling\TimeRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out which start times a customer may actually book.
 *
 * Availability is derived, never stored. Every one of the constraints in
 * MASTER_SPEC section 10 is applied here in order:
 *
 *   1. salon opening hours          6. leave and days off
 *   2. staff working schedule       7. holidays and closures
 *   3. staff-service capability     8. special schedule exceptions
 *   4. existing appointments        9. booking rules
 *   5. staff breaks                10. buffer time
 *
 * A result from this class is a *candidate*, not a reservation. Booking must
 * revalidate immediately before writing, under a lock, because the schedule can
 * change between a customer seeing a slot and choosing it (MASTER_SPEC
 * section 8). ConflictDetector is what does that.
 */
class AvailabilityService
{
    public function __construct(
        private readonly SalonHoursResolver $salonHours,
        private readonly StaffScheduleResolver $staffSchedule,
    ) {}

    /**
     * Bookable start times for one staff member, one day, one set of services.
     *
     * @param  Collection<int, Service>  $services
     * @return Collection<int, Slot>
     */
    public function slotsFor(
        Staff $staff,
        Collection $services,
        CarbonImmutable $localDate,
        ?BookingRuleChecker $rules = null,
        ?CarbonImmutable $now = null,
    ): Collection {
        $rules ??= new BookingRuleChecker;
        $now ??= CarbonImmutable::now();

        $free = $this->freeRangesFor($staff, $services, $localDate, $rules);

        if ($free === []) {
            return collect();
        }

        $duration = $this->totalDuration($services);

        return $this->walkSlots($free, $duration, $rules, $now);
    }

    /**
     * The unbroken stretches of time this staff member has left on a day, after
     * every constraint except the booking-rule window.
     *
     * Exposed separately because staff-facing schedule views want the gaps
     * themselves rather than bookable start times.
     *
     * @param  Collection<int, Service>  $services
     * @return list<TimeRange>
     */
    public function freeRangesFor(
        Staff $staff,
        Collection $services,
        CarbonImmutable $localDate,
        ?BookingRuleChecker $rules = null,
        ?int $ignoreAppointmentId = null,
    ): array {
        $rules ??= new BookingRuleChecker;

        // 3. Capability. A stylist who cannot do one of the chosen services has
        //    no availability for this combination at all.
        if (! $this->staffSchedule->canPerformAll($staff, $services)) {
            return [];
        }

        if (! $staff->is_active || ! $staff->is_bookable) {
            return [];
        }

        $duration = $this->totalDuration($services);

        if (! $rules->allowsDuration($duration)) {
            return [];
        }

        // 1. Salon hours, already accounting for 7 and 8.
        $open = $this->salonHours->openRangesFor($localDate);

        if ($open === []) {
            return [];
        }

        // 2. Staff roster.
        $working = $this->staffSchedule->workingRangesFor($staff, $localDate);

        if ($working === []) {
            return [];
        }

        $ranges = TimeRange::intersectAll($open, $working);

        // 5, 6, 7. Breaks, leave, days off, and salon-wide closures.
        $ranges = TimeRange::subtractAll($ranges, $this->staffSchedule->blockedRangesFor($staff, $localDate));

        // 4 and 10. Existing appointments, widened by the buffer.
        $ranges = TimeRange::subtractAll($ranges, $this->staffSchedule->committedRangesFor(
            $staff,
            $localDate,
            $rules->bufferMinutes(),
            $ignoreAppointmentId,
        ));

        // A gap shorter than the appointment is not availability.
        return array_values(array_filter(
            $ranges,
            fn (TimeRange $range) => $range->durationMinutes() >= $duration,
        ));
    }

    /**
     * Whether one exact window is bookable.
     *
     * This is the check booking revalidates with. It answers a narrower question
     * than slotsFor: not "what could they book" but "is this precise window still
     * free right now".
     *
     * @param  Collection<int, Service>  $services
     */
    public function canAccommodate(
        Staff $staff,
        Collection $services,
        CarbonImmutable $startsAt,
        ?BookingRuleChecker $rules = null,
        ?CarbonImmutable $now = null,
        ?int $ignoreAppointmentId = null,
    ): bool {
        $rules ??= new BookingRuleChecker;
        $now ??= CarbonImmutable::now();

        $duration = $this->totalDuration($services);

        if ($duration <= 0 || ! $rules->allowsDuration($duration)) {
            return false;
        }

        if (! $rules->allowsStartAt($startsAt, $now)) {
            return false;
        }

        $wanted = new TimeRange($startsAt, $startsAt->addMinutes($duration));

        $localDate = $startsAt->setTimezone(config('salon.timezone'));

        foreach ($this->freeRangesFor($staff, $services, $localDate, $rules, $ignoreAppointmentId) as $range) {
            if ($range->contains($wanted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bookable start times across a range of days, keyed by salon-local date.
     *
     * @param  Collection<int, Service>  $services
     * @return Collection<string, Collection<int, Slot>>
     */
    public function slotsAcross(
        Staff $staff,
        Collection $services,
        CarbonImmutable $from,
        int $days,
        ?BookingRuleChecker $rules = null,
        ?CarbonImmutable $now = null,
    ): Collection {
        $rules ??= new BookingRuleChecker;
        $now ??= CarbonImmutable::now();

        $result = collect();

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->addDays($offset);

            $result->put(
                $date->setTimezone(config('salon.timezone'))->toDateString(),
                $this->slotsFor($staff, $services, $date, $rules, $now),
            );
        }

        return $result;
    }

    /**
     * @param  Collection<int, Service>  $services
     */
    public function totalDuration(Collection $services): int
    {
        return (int) $services->sum('duration_minutes');
    }

    /**
     * @param  Collection<int, Service>  $services
     */
    public function totalPrice(Collection $services): string
    {
        return number_format((float) $services->sum(fn (Service $service) => (float) $service->price), 2, '.', '');
    }

    /**
     * Step through each free stretch at the configured interval, emitting every
     * start time where the whole appointment still fits.
     *
     * @param  list<TimeRange>  $ranges
     * @return Collection<int, Slot>
     */
    private function walkSlots(array $ranges, int $duration, BookingRuleChecker $rules, CarbonImmutable $now): Collection
    {
        $interval = $rules->slotIntervalMinutes();
        $earliest = $rules->earliestStart($now);
        $latest = $rules->latestStart($now);

        $slots = collect();

        foreach ($ranges as $range) {
            // Start on the interval grid so offered times read as 9:00, 9:15,
            // rather than drifting off whatever time the last booking ended.
            $cursor = $this->alignToInterval($range->start, $interval);

            while ($cursor->addMinutes($duration) <= $range->end) {
                if ($cursor >= $earliest && $cursor <= $latest) {
                    $slots->push(new Slot($cursor, $cursor->addMinutes($duration)));
                }

                $cursor = $cursor->addMinutes($interval);
            }
        }

        return $slots->sortBy(fn (Slot $slot) => $slot->startsAt->getTimestamp())->values();
    }

    /**
     * Round up to the next interval boundary, measured from the salon's local
     * midnight so the grid lines up with the wall clock rather than with UTC.
     */
    private function alignToInterval(CarbonImmutable $instant, int $interval): CarbonImmutable
    {
        $local = $instant->setTimezone(config('salon.timezone'));
        $midnight = $local->startOfDay();

        $minutes = (int) $midnight->diffInMinutes($local);
        $aligned = (int) (ceil($minutes / $interval) * $interval);

        return $midnight->addMinutes($aligned)->utc();
    }
}
