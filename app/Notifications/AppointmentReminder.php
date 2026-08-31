<?php

namespace App\Notifications;

class AppointmentReminder extends AppointmentNotification
{
    protected function kind(): string
    {
        return 'reminder';
    }

    protected function subject(): string
    {
        return 'Your appointment is tomorrow';
    }

    protected function headline(): string
    {
        return 'A reminder that your appointment is coming up.';
    }

    /**
     * @return list<string>
     */
    protected function mailNotes(): array
    {
        return ['Please let us know as soon as you can if you cannot make it.'];
    }
}
