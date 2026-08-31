<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves an appointment to a new time, or a new stylist.
 *
 * A reschedule creates a *new* appointment and cancels the original, rather than
 * editing times in place. The schema was built for this in Phase 1:
 * appointments.rescheduled_from_id points at the appointment that was replaced.
 *
 * Keeping both records means the salon can still see that a customer moved a
 * booking and when, which an in-place edit would erase. It also means the new
 * appointment goes through exactly the same locked revalidation as any other
 * booking, so a reschedule can never sidestep the double-booking protection.
 */
class RescheduleService
{
    public function __construct(
        private readonly BookingService $booking,
        private readonly ConflictDetector $conflicts,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws BookingException
     */
    public function reschedule(
        Appointment $original,
        CarbonImmutable $startsAt,
        User $actor,
        ?Staff $staff = null,
    ): Appointment {
        if (! $original->status->blocksAvailability()) {
            throw new BookingException(
                'This appointment has already been cancelled and cannot be moved.',
                'starts_at',
            );
        }

        $staff ??= $original->staff;
        $services = $this->servicesFor($original);

        if ($services->isEmpty()) {
            throw new BookingException(
                'The services on this appointment are no longer in the menu, so it cannot be moved automatically.',
                'starts_at',
            );
        }

        return DB::transaction(function () use ($original, $startsAt, $actor, $staff, $services) {
            // Free the original first, inside the same transaction, so its own
            // slot is available to move into. Booking then revalidates under the
            // lock exactly as a fresh booking would.
            $original->status = AppointmentStatus::Cancelled;
            $original->cancelled_at ??= now();
            $original->cancelled_by_id ??= $actor->getKey();
            $original->cancellation_reason = 'Rescheduled';
            $original->save();

            $replacement = $this->booking->book(
                customer: $original->customer,
                staff: $staff,
                services: $services,
                startsAt: $startsAt,
                notes: $original->notes,
                bookedBy: $actor,
                source: $original->source,
            );

            $replacement->rescheduled_from_id = $original->getKey();
            $replacement->save();

            $this->audit->record('appointment.rescheduled', $replacement, [
                'from_reference' => $original->reference,
                'to_reference' => $replacement->reference,
            ], $actor);

            return $replacement;
        });
    }

    /**
     * Whether a window is free for this appointment to move into.
     *
     * The appointment's own slot is ignored, so "move to the same time with a
     * different stylist" is not blocked by itself.
     */
    public function canMoveTo(Appointment $appointment, Staff $staff, CarbonImmutable $startsAt): bool
    {
        $duration = $appointment->total_duration_minutes;

        return ! $this->conflicts->hasConflict(
            $staff,
            $startsAt,
            $startsAt->addMinutes($duration),
            (new BookingRuleChecker)->bufferMinutes(),
            $appointment->getKey(),
        );
    }

    /**
     * The live services behind an appointment's items.
     *
     * A soft-deleted service has no live row, so it drops out; the caller is
     * told rather than silently rescheduling a shorter appointment.
     *
     * @return Collection<int, Service>
     */
    public function servicesFor(Appointment $appointment): Collection
    {
        $ids = $appointment->items->pluck('service_id')->filter()->all();

        if ($ids === []) {
            return collect();
        }

        $services = Service::query()->whereKey($ids)->get();

        // Every item must still resolve, otherwise the rebooked appointment
        // would quietly lose a service.
        return $services->count() === count(array_unique($ids)) ? $services : collect();
    }
}
