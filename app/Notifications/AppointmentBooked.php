<?php

namespace App\Notifications;

class AppointmentBooked extends AppointmentNotification
{
    protected function kind(): string
    {
        return 'booked';
    }

    protected function subject(): string
    {
        return 'Your appointment is booked';
    }

    protected function headline(): string
    {
        return 'Your appointment is booked and waiting for the salon to confirm it.';
    }

    /**
     * @return list<string>
     */
    protected function mailNotes(): array
    {
        return ['We will send another note once the salon has confirmed the time.'];
    }
}
