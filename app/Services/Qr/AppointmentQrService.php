<?php

namespace App\Services\Qr;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;

/**
 * Appointment QR codes (MASTER_SPEC section 20).
 *
 * The code encodes nothing but a URL containing a random opaque token. It holds
 * no name, no date, no reference, and no id, so a photographed or forwarded code
 * reveals nothing on its own and cannot be reversed into an appointment.
 *
 * Scanning only resolves through an authenticated staff route. The QR is a
 * shortcut to an appointment the salon could already look up by reference, never
 * a credential in its own right, and normal lookup keeps working without it.
 */
class AppointmentQrService
{
    /**
     * How long after an appointment a code stays useful.
     *
     * Nothing is stored as an expiry: the token is checked against the
     * appointment's own time, so an old code simply stops resolving.
     */
    private const VALID_HOURS_AFTER = 24;

    /** How early a code may be scanned before the appointment. */
    private const VALID_DAYS_BEFORE = 7;

    public function urlFor(Appointment $appointment): string
    {
        return route('qr.resolve', ['token' => $appointment->qr_token]);
    }

    /**
     * An SVG QR code. SVG rather than a raster so it stays crisp on a phone
     * screen at any size, and needs no image extension to produce.
     */
    public function svgFor(Appointment $appointment, int $size = 260): string
    {
        $writer = new Writer(
            new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)
        );

        return $writer->writeString($this->urlFor($appointment));
    }

    /**
     * Find the appointment a scanned token belongs to.
     *
     * Returns null for anything unknown, so an invalid code and a code for
     * someone else's appointment are indistinguishable to whoever scanned it.
     */
    public function resolve(string $token): ?Appointment
    {
        if (trim($token) === '') {
            return null;
        }

        return Appointment::query()
            ->where('qr_token', $token)
            ->with(['customer.customerProfile', 'staff.user:id,name', 'items'])
            ->first();
    }

    /**
     * Whether a resolved code is still usable for check-in.
     *
     * A code is not a permanent key to an appointment: it is only meaningful
     * around the time of the visit, and once the appointment reaches a terminal
     * status there is nothing left to do with it.
     */
    public function isUsable(Appointment $appointment, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        if ($appointment->status->isTerminal()) {
            return false;
        }

        if ($now->greaterThan($appointment->ends_at->addHours(self::VALID_HOURS_AFTER))) {
            return false;
        }

        return ! $now->lessThan($appointment->starts_at->subDays(self::VALID_DAYS_BEFORE));
    }

    /**
     * Why a code cannot be used, phrased for the person holding the scanner.
     */
    public function unusableReason(Appointment $appointment, ?CarbonImmutable $now = null): ?string
    {
        $now ??= CarbonImmutable::now();

        if ($appointment->status->isTerminal()) {
            return match ($appointment->status) {
                AppointmentStatus::Cancelled => 'This appointment was cancelled.',
                AppointmentStatus::Completed => 'This appointment has already been completed.',
                default => 'This appointment was marked as a no show.',
            };
        }

        if ($now->greaterThan($appointment->ends_at->addHours(self::VALID_HOURS_AFTER))) {
            return 'This code is for an appointment that has already passed.';
        }

        if ($now->lessThan($appointment->starts_at->subDays(self::VALID_DAYS_BEFORE))) {
            return 'This code is not active yet. It works from a week before the appointment.';
        }

        return null;
    }
}
