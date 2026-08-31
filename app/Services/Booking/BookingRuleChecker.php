<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\BookingRule;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The admin-configurable booking policy, applied server-side.
 *
 * The same rules gate both what availability offers and what a booking request is
 * allowed to do, so a customer can never confirm a slot the policy would refuse
 * (MASTER_SPEC section 11).
 */
class BookingRuleChecker
{
    private BookingRule $rules;

    public function __construct(?BookingRule $rules = null)
    {
        $this->rules = $rules ?? BookingRule::current();
    }

    public function rules(): BookingRule
    {
        return $this->rules;
    }

    public function slotIntervalMinutes(): int
    {
        return max(5, $this->rules->slot_interval_minutes);
    }

    public function bufferMinutes(): int
    {
        return max(0, $this->rules->buffer_minutes);
    }

    /**
     * The earliest instant a new booking may start.
     */
    public function earliestStart(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->addMinutes($this->rules->min_advance_minutes);
    }

    /**
     * The latest instant a new booking may start.
     */
    public function latestStart(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->addDays($this->rules->max_advance_days)->endOfDay();
    }

    /**
     * Whether a start time sits inside the bookable window.
     */
    public function allowsStartAt(DateTimeInterface $start, ?CarbonImmutable $now = null): bool
    {
        $instant = CarbonImmutable::instance($start)->utc();

        return $instant >= $this->earliestStart($now) && $instant <= $this->latestStart($now);
    }

    /**
     * Whether an appointment of this length is permitted at all.
     */
    public function allowsDuration(int $minutes): bool
    {
        if ($minutes <= 0) {
            return false;
        }

        $max = $this->rules->max_duration_minutes;

        return $max === null || $minutes <= $max;
    }

    /**
     * Cancellation is refused once the deadline has passed, so the salon is not
     * left with an unfillable gap.
     */
    public function allowsCancellation(Appointment $appointment, ?CarbonImmutable $now = null): bool
    {
        return $this->beyondDeadline(
            $appointment,
            $this->rules->cancellation_deadline_hours,
            $now,
        );
    }

    public function allowsRescheduling(Appointment $appointment, ?CarbonImmutable $now = null): bool
    {
        return $this->beyondDeadline(
            $appointment,
            $this->rules->reschedule_deadline_hours,
            $now,
        );
    }

    /**
     * How long before the appointment the customer must act.
     */
    public function cancellationDeadlineFor(Appointment $appointment): CarbonImmutable
    {
        return CarbonImmutable::instance($appointment->starts_at)
            ->utc()
            ->subHours($this->rules->cancellation_deadline_hours);
    }

    public function reschedulingDeadlineFor(Appointment $appointment): CarbonImmutable
    {
        return CarbonImmutable::instance($appointment->starts_at)
            ->utc()
            ->subHours($this->rules->reschedule_deadline_hours);
    }

    private function beyondDeadline(Appointment $appointment, int $hours, ?CarbonImmutable $now): bool
    {
        $deadline = CarbonImmutable::instance($appointment->starts_at)->utc()->subHours($hours);

        return ($now ?? CarbonImmutable::now())->utc() < $deadline;
    }
}
