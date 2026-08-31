<?php

namespace App\Notifications;

class AppointmentConfirmed extends AppointmentNotification
{
    protected function kind(): string
    {
        return 'confirmed';
    }

    protected function subject(): string
    {
        return 'Your appointment is confirmed';
    }

    protected function headline(): string
    {
        return 'The salon has confirmed your appointment. We look forward to seeing you.';
    }
}
