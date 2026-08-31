<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A booking was refused for a domain reason rather than a malformed request.
 *
 * These are expected outcomes, not faults: the slot was taken while the customer
 * was deciding, the stylist stopped offering the service, the notice period
 * lapsed. Each carries a message meant to be read by the customer, and maps onto
 * the form field that caused it so the error appears next to the right control.
 */
class BookingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $field = 'starts_at',
    ) {
        parent::__construct($message);
    }

    public static function slotTaken(): self
    {
        return new self(
            'That time was booked while you were choosing. Please pick another slot.',
            'starts_at',
        );
    }

    public static function slotUnavailable(): self
    {
        return new self(
            'That time is no longer available. Please pick another slot.',
            'starts_at',
        );
    }

    public static function outsideBookingWindow(): self
    {
        return new self(
            'That time is outside the period we are taking bookings for.',
            'starts_at',
        );
    }

    public static function tooLong(): self
    {
        return new self(
            'These services add up to longer than we can book in one appointment.',
            'service_ids',
        );
    }

    public static function noServices(): self
    {
        return new self('Please choose at least one service.', 'service_ids');
    }

    public static function serviceUnavailable(): self
    {
        return new self(
            'One of the services you chose is no longer available.',
            'service_ids',
        );
    }

    public static function staffUnavailable(): self
    {
        return new self(
            'That stylist is no longer taking bookings.',
            'staff_id',
        );
    }

    public static function staffCannotPerform(): self
    {
        return new self(
            'That stylist does not offer every service you chose.',
            'staff_id',
        );
    }

    /**
     * Surface as a normal validation error, so the booking form shows it in
     * place rather than as a server error page.
     */
    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages([
            $this->field => $this->getMessage(),
        ]);
    }
}
