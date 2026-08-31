<?php

namespace App\Services\Notifications;

use App\Models\Appointment;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentReminder;

/**
 * Decides who is told what, in one place.
 *
 * Every caller sends notifications *after* its transaction has committed. Doing
 * it inside would mean a customer could receive confirmation of a booking that
 * then rolled back, and there is no way to unsend that.
 *
 * The relations each notification reads are loaded here rather than left to lazy
 * loading, so building a message can never fire a query per notification.
 */
class AppointmentNotifier
{
    public function booked(Appointment $appointment): void
    {
        $this->notifyCustomer($appointment, new AppointmentBooked($this->prepared($appointment)));
    }

    public function confirmed(Appointment $appointment): void
    {
        $this->notifyCustomer($appointment, new AppointmentConfirmed($this->prepared($appointment)));
    }

    public function cancelled(Appointment $appointment): void
    {
        $this->notifyCustomer($appointment, new AppointmentCancelled($this->prepared($appointment)));
    }

    public function reminder(Appointment $appointment): void
    {
        $this->notifyCustomer($appointment, new AppointmentReminder($this->prepared($appointment)));
    }

    private function notifyCustomer(Appointment $appointment, object $notification): void
    {
        $customer = $appointment->customer;

        // A deactivated account is not somewhere to keep sending mail.
        if ($customer === null || ! $customer->is_active) {
            return;
        }

        $customer->notify($notification);
    }

    private function prepared(Appointment $appointment): Appointment
    {
        return $appointment->loadMissing(['items', 'staff.user:id,name', 'customer:id,name,email,is_active']);
    }
}
