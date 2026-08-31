<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Staff;
use App\Services\Scheduling\TimeRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detects double bookings, and holds the lock that makes the check trustworthy.
 *
 * ## Why a lock is needed
 *
 * Availability is read-then-write: check the slot is free, then insert. Two
 * customers can both pass the check before either inserts, and both bookings
 * land. MASTER_SPEC section 8 is explicit that an availability check alone is
 * insufficient.
 *
 * ## The strategy, and why this one
 *
 * Inside the booking transaction, the staff row is locked with SELECT ... FOR
 * UPDATE before conflicts are re-checked. A second request for the same stylist
 * blocks on that row until the first transaction commits, then re-reads and sees
 * the appointment that was just written.
 *
 * The alternative is a range lock over the appointments table, relying on InnoDB
 * gap locks to stop an insert landing inside the window being examined. That is
 * more precise but its correctness depends on the isolation level and on the
 * exact index the planner chooses, which makes it easy to get subtly wrong.
 *
 * Locking the stylist serialises bookings *for that one stylist only*. Two
 * customers booking different stylists never contend. For a salon, where the
 * realistic peak is a handful of concurrent bookings per stylist, that costs
 * nothing and is far easier to reason about and to prove correct.
 */
class ConflictDetector
{
    /**
     * Take the lock for a staff member, then run the caller's work.
     *
     * Must be called inside a transaction, otherwise the lock is released the
     * moment the select finishes and protects nothing.
     *
     * @template T
     *
     * @param  \Closure(): T  $work
     * @return T
     */
    public function withStaffLocked(Staff $staff, \Closure $work): mixed
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException(
                'withStaffLocked() must run inside a transaction, or the lock is released immediately.'
            );
        }

        DB::table('staff')->where('id', $staff->getKey())->lockForUpdate()->first();

        return $work();
    }

    /**
     * Whether anything already occupies this window for this staff member.
     *
     * Buffer is applied to the *existing* appointments rather than to the
     * candidate, so a booking may still start exactly when the salon opens.
     */
    public function hasConflict(
        Staff $staff,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $bufferMinutes = 0,
        ?int $ignoreAppointmentId = null,
    ): bool {
        return $this->conflictingAppointments(
            $staff,
            $startsAt,
            $endsAt,
            $bufferMinutes,
            $ignoreAppointmentId,
        )->isNotEmpty();
    }

    /**
     * The appointments standing in the way, for reporting a useful error.
     *
     * @return Collection<int, Appointment>
     */
    public function conflictingAppointments(
        Staff $staff,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $bufferMinutes = 0,
        ?int $ignoreAppointmentId = null,
    ) {
        // Widening the candidate window by the buffer is equivalent to widening
        // every existing appointment, and needs only one query.
        $window = (new TimeRange($startsAt, $endsAt))->expandedBy($bufferMinutes);

        return Appointment::query()
            ->where('staff_id', $staff->getKey())
            ->blocking()
            ->overlapping($window->start, $window->end)
            ->when($ignoreAppointmentId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->orderBy('starts_at')
            ->get();
    }
}
