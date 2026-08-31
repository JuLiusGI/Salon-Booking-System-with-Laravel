<?php

namespace App\Notifications;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared shape for everything the salon tells a customer about an appointment.
 *
 * The payload is deliberately thin. A database notification is rendered in the
 * browser and a mail one leaves the building, so neither carries anything beyond
 * what the message needs to make sense: no allergies, no internal notes, no
 * contact details, no QR token (MASTER_SPEC sections 17 and 21).
 *
 * Subclasses supply the wording; everything else is here so a new notification
 * cannot accidentally widen what gets sent.
 */
abstract class AppointmentNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // Database for the in-app list, mail for the record. In development the
        // mail driver is the log, so nothing actually leaves the machine.
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return array_merge([
            'type' => $this->kind(),
            'reference' => $this->appointment->reference,
            'headline' => $this->headline(),
            'date' => $this->localStart()->format('l, j F Y'),
            'time' => $this->localStart()->format('g:i A'),
            'staff_name' => $this->appointment->staff->user->name,
            'services' => $this->appointment->items->pluck('service_name')->all(),
            'url' => route('appointments.show', $this->appointment->reference),
        ], $this->extra());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->headline())
            ->line('**When:** '.$this->localStart()->format('l, j F Y').' at '.$this->localStart()->format('g:i A'))
            ->line('**With:** '.$this->appointment->staff->user->name)
            ->line('**Services:** '.$this->appointment->items->pluck('service_name')->join(', '))
            ->line('**Reference:** '.$this->appointment->reference);

        foreach ($this->mailNotes() as $note) {
            $mail->line($note);
        }

        return $mail
            ->action('View your appointment', route('appointments.show', $this->appointment->reference))
            ->line('Thank you for choosing us.');
    }

    /**
     * A short machine-readable kind, used to pick an icon and colour in the UI.
     */
    abstract protected function kind(): string;

    abstract protected function subject(): string;

    abstract protected function headline(): string;

    /**
     * Extra lines for the email only.
     *
     * @return list<string>
     */
    protected function mailNotes(): array
    {
        return [];
    }

    /**
     * Extra keys for the stored payload. Must stay free of personal data.
     *
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [];
    }

    protected function localStart(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->appointment->starts_at)
            ->setTimezone(config('salon.timezone'));
    }
}
