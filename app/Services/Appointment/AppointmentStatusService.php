<?php

namespace App\Services\Appointment;

use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentTransitionException;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Moves an appointment through its lifecycle.
 *
 * The permitted moves live on AppointmentStatus, declared once in Phase 1 and
 * never duplicated. This class enforces that map, records the timestamp each
 * move implies, and audits it. Nothing else in the application may write the
 * status column, which is why it is guarded against mass assignment.
 */
class AppointmentStatusService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AppointmentTransitionException
     */
    public function transition(
        Appointment $appointment,
        AppointmentStatus $target,
        User $actor,
        ?string $reason = null,
    ): Appointment {
        $from = $appointment->status;

        if ($from === $target) {
            throw AppointmentTransitionException::alreadyInStatus($target);
        }

        if ($from->isTerminal()) {
            throw AppointmentTransitionException::terminal($from);
        }

        if (! $from->canTransitionTo($target)) {
            throw AppointmentTransitionException::notAllowed($from, $target);
        }

        return DB::transaction(function () use ($appointment, $from, $target, $actor, $reason) {
            $appointment->status = $target;

            // Each status carries the moment it happened. Recording it here
            // keeps the timestamp and the status from ever disagreeing.
            match ($target) {
                AppointmentStatus::CheckedIn => $appointment->checked_in_at ??= now(),
                AppointmentStatus::InProgress => $appointment->started_at ??= now(),
                AppointmentStatus::Completed => $appointment->completed_at ??= now(),
                AppointmentStatus::Cancelled => $this->recordCancellation($appointment, $actor, $reason),
                default => null,
            };

            $appointment->save();

            $this->audit->record('appointment.status_changed', $appointment, [
                'reference' => $appointment->reference,
                'from' => $from->value,
                'to' => $target->value,
            ], $actor);

            return $appointment;
        });
    }

    /**
     * Statuses this actor is allowed to move this appointment to right now.
     *
     * Used to build the action buttons, so the interface can only ever offer a
     * move the server would accept.
     *
     * @return list<AppointmentStatus>
     */
    public function availableTransitions(Appointment $appointment, User $actor): array
    {
        return array_values(array_filter(
            $appointment->status->allowedTransitions(),
            fn (AppointmentStatus $target) => $actor->can('transition', [$appointment, $target]),
        ));
    }

    private function recordCancellation(Appointment $appointment, User $actor, ?string $reason): void
    {
        $appointment->cancelled_at ??= now();
        $appointment->cancelled_by_id ??= $actor->getKey();
        $appointment->cancellation_reason = $reason ?: $appointment->cancellation_reason;
    }
}
