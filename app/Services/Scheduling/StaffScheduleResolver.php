<?php

namespace App\Services\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleExceptionType;
use App\Models\Appointment;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffAvailability;
use Carbon\CarbonImmutable;

/**
 * Works out when one staff member is actually free on a given day.
 *
 * Working hours are wall clock for the same reason salon hours are, and are
 * anchored to the date here before anything is compared.
 */
class StaffScheduleResolver
{
    /**
     * The staff member's rostered working period(s) for a salon-local date,
     * before breaks, leave, and existing appointments are removed.
     *
     * @return list<TimeRange>
     */
    public function workingRangesFor(Staff $staff, CarbonImmutable $localDate): array
    {
        if (! $staff->is_active) {
            return [];
        }

        $dayStart = $this->localMidnight($localDate);

        return StaffAvailability::query()
            ->where('staff_id', $staff->getKey())
            ->where('day_of_week', $dayStart->dayOfWeek)
            ->active()
            ->get()
            ->map(function (StaffAvailability $block) use ($dayStart): ?TimeRange {
                $start = $dayStart->setTimeFromTimeString((string) $block->starts_at);
                $end = $dayStart->setTimeFromTimeString((string) $block->ends_at);

                return $end > $start ? new TimeRange($start->utc(), $end->utc()) : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Everything that removes time from this staff member's day: their own
     * breaks, leave and days off, plus salon-wide holidays and closures.
     *
     * @return list<TimeRange>
     */
    public function blockedRangesFor(Staff $staff, CarbonImmutable $localDate): array
    {
        $dayStart = $this->localMidnight($localDate);
        $dayEnd = $dayStart->addDay();

        $blockingTypes = collect(ScheduleExceptionType::cases())
            ->filter(fn (ScheduleExceptionType $type) => $type->blocksTime())
            ->values();

        return ScheduleException::query()
            ->whereIn('type', $blockingTypes)
            ->where(function ($query) use ($staff) {
                $query->where('staff_id', $staff->getKey())
                    // A salon-wide closure blocks everyone.
                    ->orWhereNull('staff_id');
            })
            ->overlapping($dayStart->utc(), $dayEnd->utc())
            ->get()
            ->map(fn (ScheduleException $exception) => new TimeRange($exception->starts_at, $exception->ends_at))
            ->all();
    }

    /**
     * Time already committed to appointments, grown by the buffer so the salon
     * gets its turnaround gap between clients.
     *
     * Cancelled and no-show appointments release their slot, which is what
     * AppointmentStatus::blocksAvailability() decides.
     *
     * @return list<TimeRange>
     */
    public function committedRangesFor(
        Staff $staff,
        CarbonImmutable $localDate,
        int $bufferMinutes = 0,
        ?int $ignoreAppointmentId = null,
    ): array {
        $dayStart = $this->localMidnight($localDate);

        // Widen the lookup window by the buffer, so an appointment finishing
        // just before midnight can still block the first slot of the next day.
        $from = $dayStart->subMinutes($bufferMinutes)->utc();
        $to = $dayStart->addDay()->addMinutes($bufferMinutes)->utc();

        return Appointment::query()
            ->where('staff_id', $staff->getKey())
            ->blocking()
            ->overlapping($from, $to)
            ->when($ignoreAppointmentId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->get()
            ->map(fn (Appointment $appointment) => (new TimeRange(
                $appointment->starts_at,
                $appointment->ends_at,
            ))->expandedBy($bufferMinutes))
            ->all();
    }

    /**
     * Whether the staff member can perform every one of these services.
     *
     * @param  iterable<Service>  $services
     */
    public function canPerformAll(Staff $staff, iterable $services): bool
    {
        $assigned = $staff->services()->pluck('services.id')->all();

        foreach ($services as $service) {
            if (! in_array($service->getKey(), $assigned, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Statuses that hold a slot. Exposed so callers and tests can reason about
     * the same rule the queries use.
     *
     * @return list<AppointmentStatus>
     */
    public static function blockingStatuses(): array
    {
        return array_values(array_filter(
            AppointmentStatus::cases(),
            fn (AppointmentStatus $status) => $status->blocksAvailability(),
        ));
    }

    private function localMidnight(CarbonImmutable $date): CarbonImmutable
    {
        return $date->setTimezone(config('salon.timezone'))->startOfDay();
    }
}
