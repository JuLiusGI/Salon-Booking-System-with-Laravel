<?php

namespace App\Services\Booking;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\Notifications\AppointmentNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates appointments.
 *
 * The order of operations here is the whole point, and follows MASTER_SPEC
 * section 8:
 *
 *   1. Cheap checks first, outside the transaction, so obviously bad requests
 *      never take a lock.
 *   2. Open a transaction and lock the stylist.
 *   3. Revalidate availability *after* taking the lock. The slot the customer
 *      saw was true when it was rendered, and may not be true now.
 *   4. Write the appointment and its items together, or write nothing.
 *
 * Step 3 is what stops two customers booking the same stylist for the same time.
 * Skipping it, or doing it before the lock, leaves a window where both requests
 * pass their check and both inserts land.
 */
class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ConflictDetector $conflicts,
        private readonly AppointmentNotifier $notifier,
    ) {}

    /**
     * Book an appointment, or throw explaining why it could not be booked.
     *
     * @param  Collection<int, Service>  $services
     *
     * @throws BookingException
     */
    public function book(
        User $customer,
        Staff $staff,
        Collection $services,
        CarbonImmutable $startsAt,
        ?string $notes = null,
        ?User $bookedBy = null,
        AppointmentSource $source = AppointmentSource::Online,
    ): Appointment {
        $rules = new BookingRuleChecker;

        $this->assertBookable($staff, $services, $startsAt, $rules);

        $duration = $this->availability->totalDuration($services);
        $endsAt = $startsAt->addMinutes($duration);

        $appointment = DB::transaction(function () use (
            $customer, $staff, $services, $startsAt, $endsAt,
            $duration, $notes, $bookedBy, $source, $rules
        ) {
            return $this->conflicts->withStaffLocked($staff, function () use (
                $customer, $staff, $services, $startsAt, $endsAt,
                $duration, $notes, $bookedBy, $source, $rules
            ) {
                // Re-read the stylist inside the lock: they may have been
                // deactivated between the form loading and this request.
                $staff->refresh();

                if (! $staff->is_active || ! $staff->is_bookable) {
                    throw BookingException::staffUnavailable();
                }

                if ($this->conflicts->hasConflict($staff, $startsAt, $endsAt, $rules->bufferMinutes())) {
                    throw BookingException::slotTaken();
                }

                // The conflict check only looks at other appointments. This
                // re-runs the full engine, so a break or closure added moments
                // ago is caught too.
                if (! $this->availability->canAccommodate($staff, $services, $startsAt, $rules)) {
                    throw BookingException::slotUnavailable();
                }

                return $this->write(
                    $customer, $staff, $services, $startsAt, $endsAt,
                    $duration, $notes, $bookedBy, $source,
                );
            });
        });

        // Only once the booking is committed. Telling a customer about an
        // appointment that then rolled back cannot be undone.
        $this->notifier->booked($appointment);

        return $appointment;
    }

    /**
     * Checks that do not need a lock, run first so a hopeless request is cheap.
     *
     * @param  Collection<int, Service>  $services
     *
     * @throws BookingException
     */
    private function assertBookable(
        Staff $staff,
        Collection $services,
        CarbonImmutable $startsAt,
        BookingRuleChecker $rules,
    ): void {
        if ($services->isEmpty()) {
            throw BookingException::noServices();
        }

        if ($services->contains(fn (Service $service) => ! $service->is_active)) {
            throw BookingException::serviceUnavailable();
        }

        if (! $staff->is_active || ! $staff->is_bookable) {
            throw BookingException::staffUnavailable();
        }

        $assigned = $staff->services()->pluck('services.id')->all();

        foreach ($services as $service) {
            if (! in_array($service->getKey(), $assigned, true)) {
                throw BookingException::staffCannotPerform();
            }
        }

        $duration = $this->availability->totalDuration($services);

        if (! $rules->allowsDuration($duration)) {
            throw BookingException::tooLong();
        }

        if (! $rules->allowsStartAt($startsAt)) {
            throw BookingException::outsideBookingWindow();
        }
    }

    /**
     * @param  Collection<int, Service>  $services
     */
    private function write(
        User $customer,
        Staff $staff,
        Collection $services,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $duration,
        ?string $notes,
        ?User $bookedBy,
        AppointmentSource $source,
    ): Appointment {
        $appointment = new Appointment([
            'reference' => Appointment::generateReference(),
            'qr_token' => Appointment::generateQrToken(),
            'customer_id' => $customer->getKey(),
            'staff_id' => $staff->getKey(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'source' => $source,
            'total_duration_minutes' => $duration,
            'total_price' => $this->availability->totalPrice($services),
            'notes' => $notes,
            'booked_by_id' => ($bookedBy ?? $customer)->getKey(),
        ]);

        // Status is guarded against mass assignment, so it is set deliberately
        // here rather than accepted from anywhere near a request.
        $appointment->status = AppointmentStatus::Pending;
        $appointment->save();

        foreach ($services->values() as $position => $service) {
            // Snapshot, so editing or removing the service later cannot rewrite
            // what this appointment cost or how long it was.
            $item = AppointmentItem::fromService($service, $position);
            $item->appointment_id = $appointment->getKey();
            $item->save();
        }

        return $appointment->fresh(['items', 'staff.user', 'customer']);
    }
}
