<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use App\Services\Notifications\AppointmentNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Reminds customers about appointments coming up tomorrow.
 *
 * Run on a schedule. Sending is idempotent: an appointment that already has a
 * reminder is skipped, so running the command twice, or catching up after the
 * scheduler was down, never sends a customer the same reminder twice.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:remind
                            {--hours=24 : How far ahead to look}
                            {--dry-run : List who would be reminded without sending}';

    protected $description = 'Send reminders for upcoming appointments';

    public function handle(AppointmentNotifier $notifier): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $now = CarbonImmutable::now();

        // A window rather than an instant, so the reminder does not depend on
        // the command running at an exact minute.
        $from = $now->addHours($hours - 1);
        $to = $now->addHours($hours);

        $due = Appointment::query()
            ->with(['customer:id,name,email,is_active', 'staff.user:id,name', 'items'])
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($due as $appointment) {
            if ($this->alreadyReminded($appointment)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  would remind {$appointment->customer->name} about {$appointment->reference}");
                $sent++;

                continue;
            }

            $notifier->reminder($appointment);
            $sent++;
        }

        $this->info(sprintf(
            '%s %d reminder(s), skipped %d already sent.',
            $dryRun ? 'Would send' : 'Sent',
            $sent,
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * Whether this customer already has a reminder for this appointment.
     *
     * Read back off the notifications table rather than stamping a column, so
     * no schema change is needed and the record of what was sent stays in the
     * one place that holds it.
     */
    private function alreadyReminded(Appointment $appointment): bool
    {
        return DatabaseNotification::query()
            ->where('type', AppointmentReminder::class)
            ->where('notifiable_type', $appointment->customer->getMorphClass())
            ->where('notifiable_id', $appointment->customer_id)
            ->where('data', 'like', '%"reference":"'.$appointment->reference.'"%')
            ->exists();
    }
}
