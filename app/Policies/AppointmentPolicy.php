<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * Who may see an appointment.
 *
 * A customer sees only their own. Salon staff see the salon's work, because
 * operating the diary requires it. Broader operational permissions arrive with
 * appointment management in a later phase.
 */
class AppointmentPolicy
{
    public function viewAny(User $actor): bool
    {
        // Customers browse their own history; staff browse the salon's.
        return true;
    }

    public function view(User $actor, Appointment $appointment): bool
    {
        if ($actor->isStaffMember()) {
            return true;
        }

        return $appointment->customer_id === $actor->getKey();
    }

    /**
     * Booking is for customers acting for themselves. Staff booking on a
     * customer's behalf is part of appointment management, not this flow.
     */
    public function create(User $actor): bool
    {
        return $actor->isCustomer();
    }
}
