<?php

namespace App\Exceptions;

use App\Enums\AppointmentStatus;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A status change was refused.
 *
 * Always a rejected request rather than a fault: the appointment moved on while
 * the page was open, or the move was never valid in the first place. Reported on
 * the form so the operator sees why.
 */
class AppointmentTransitionException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'status')
    {
        parent::__construct($message);
    }

    public static function notAllowed(AppointmentStatus $from, AppointmentStatus $to): self
    {
        return new self(
            "An appointment cannot go from {$from->label()} to {$to->label()}."
        );
    }

    public static function terminal(AppointmentStatus $status): self
    {
        return new self(
            "This appointment is {$status->label()} and cannot be changed again."
        );
    }

    public static function alreadyInStatus(AppointmentStatus $status): self
    {
        return new self("This appointment is already {$status->label()}.");
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages([
            $this->field => $this->getMessage(),
        ]);
    }
}
