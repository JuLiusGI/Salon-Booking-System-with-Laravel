<?php

namespace App\Policies;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Booking\BookingRuleChecker;

/**
 * Who may see and act on an appointment.
 *
 * Admins and receptionists run the diary and see all of it. A stylist sees the
 * work assigned to them and nothing else, which is what MASTER_SPEC section 4
 * means by "assigned appointments". A customer sees only their own.
 */
class AppointmentPolicy
{
    /**
     * Listing appointments at all.
     *
     * True for everyone, because a customer listing their own history is also a
     * list. What each person actually sees is narrowed by
     * Appointment::visibleTo().
     */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    /**
     * Reaching the salon's diary: the calendar, the appointment list, and the
     * check-in desk.
     *
     * Deliberately separate from viewAny. Sharing one ability let a customer
     * open staff screens; the data was still scoped to their own appointments,
     * but the screens were never theirs to reach.
     */
    public function viewDiary(User $actor): bool
    {
        return $actor->isStaffMember();
    }

    public function view(User $actor, Appointment $appointment): bool
    {
        if ($this->runsTheDiary($actor)) {
            return true;
        }

        if ($actor->hasRole(UserRole::Stylist)) {
            return $this->isAssignedTo($actor, $appointment);
        }

        return $appointment->customer_id === $actor->getKey();
    }

    /**
     * Booking through the customer flow. Staff book through the diary instead.
     */
    public function create(User $actor): bool
    {
        return $actor->isCustomer();
    }

    /**
     * Creating an appointment on a customer's behalf.
     */
    public function createForCustomer(User $actor): bool
    {
        return $this->runsTheDiary($actor);
    }

    /**
     * Editing the notes attached to an appointment.
     *
     * A stylist may annotate their own work; only the desk may edit anyone's.
     */
    public function update(User $actor, Appointment $appointment): bool
    {
        if ($this->runsTheDiary($actor)) {
            return true;
        }

        return $actor->hasRole(UserRole::Stylist)
            && $this->isAssignedTo($actor, $appointment);
    }

    /**
     * Moving an appointment to another status.
     *
     * The set of *valid* moves comes from AppointmentStatus. This decides who is
     * allowed to make a valid move, which is a separate question.
     */
    public function transition(User $actor, Appointment $appointment, AppointmentStatus $target): bool
    {
        if (! $appointment->status->canTransitionTo($target)) {
            return false;
        }

        if ($this->runsTheDiary($actor)) {
            return true;
        }

        if (! $actor->hasRole(UserRole::Stylist) || ! $this->isAssignedTo($actor, $appointment)) {
            return false;
        }

        // A stylist drives the appointment in front of them, but checking a
        // customer in is a front-desk job.
        return in_array($target, [
            AppointmentStatus::InProgress,
            AppointmentStatus::Completed,
            AppointmentStatus::NoShow,
        ], true);
    }

    /**
     * Cancelling.
     *
     * The desk may cancel at any point, because a phone call is a legitimate way
     * to cancel late. A customer cancelling themselves is held to the notice
     * period, which is the whole reason the rule exists.
     */
    public function cancel(User $actor, Appointment $appointment): bool
    {
        if (! $appointment->status->canTransitionTo(AppointmentStatus::Cancelled)) {
            return false;
        }

        if ($this->runsTheDiary($actor)) {
            return true;
        }

        if ($appointment->customer_id !== $actor->getKey()) {
            return false;
        }

        return (new BookingRuleChecker)->allowsCancellation($appointment);
    }

    public function reschedule(User $actor, Appointment $appointment): bool
    {
        if (! $appointment->status->blocksAvailability()) {
            return false;
        }

        if ($this->runsTheDiary($actor)) {
            return true;
        }

        if ($appointment->customer_id !== $actor->getKey()) {
            return false;
        }

        return (new BookingRuleChecker)->allowsRescheduling($appointment);
    }

    private function runsTheDiary(User $actor): bool
    {
        return $actor->isAdmin() || $actor->hasRole(UserRole::Receptionist);
    }

    private function isAssignedTo(User $actor, Appointment $appointment): bool
    {
        return $actor->staff !== null && $appointment->staff_id === $actor->staff->getKey();
    }
}
