<?php

namespace App\Notifications;

class AppointmentCancelled extends AppointmentNotification
{
    protected function kind(): string
    {
        return 'cancelled';
    }

    protected function subject(): string
    {
        return 'Your appointment has been cancelled';
    }

    protected function headline(): string
    {
        return 'This appointment has been cancelled and the time released.';
    }

    /**
     * @return list<string>
     */
    protected function mailNotes(): array
    {
        return ['If this was not what you expected, please call the salon and we will sort it out.'];
    }
}
