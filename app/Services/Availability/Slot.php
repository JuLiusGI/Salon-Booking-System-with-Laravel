<?php

namespace App\Services\Availability;

use App\Services\Scheduling\TimeRange;
use Carbon\CarbonImmutable;

/**
 * One bookable start time.
 *
 * Instants are UTC. The salon-local strings exist purely so a controller can
 * hand the frontend something already resolved in the salon's timezone, rather
 * than leaving the browser to guess.
 */
final class Slot
{
    public function __construct(
        public readonly CarbonImmutable $startsAt,
        public readonly CarbonImmutable $endsAt,
    ) {}

    public static function fromRange(TimeRange $range): self
    {
        return new self($range->start, $range->end);
    }

    public function toRange(): TimeRange
    {
        return new TimeRange($this->startsAt, $this->endsAt);
    }

    public function durationMinutes(): int
    {
        return (int) $this->startsAt->diffInMinutes($this->endsAt);
    }

    private function localStart(): CarbonImmutable
    {
        return $this->startsAt->setTimezone(config('salon.timezone'));
    }

    private function localEnd(): CarbonImmutable
    {
        return $this->endsAt->setTimezone(config('salon.timezone'));
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            // Sent to the client and echoed back when booking, so the server
            // never has to reconstruct the intended instant from a local string.
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'local_date' => $this->localStart()->toDateString(),
            'label' => $this->localStart()->format('g:i A'),
            'end_label' => $this->localEnd()->format('g:i A'),
            'duration_minutes' => $this->durationMinutes(),
        ];
    }
}
