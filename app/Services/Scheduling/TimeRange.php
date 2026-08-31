<?php

namespace App\Services\Scheduling;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A half-open instant range, [start, end).
 *
 * Half-open is the whole point: an appointment ending at 11:00 and one starting
 * at 11:00 do not overlap. Treating the boundary as closed would refuse every
 * back-to-back booking in the salon.
 *
 * Instants are always UTC. Wall-clock reasoning happens where ranges are built,
 * never here, so nothing in this class can be tripped up by a timezone.
 */
final class TimeRange
{
    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    public function __construct(DateTimeInterface $start, DateTimeInterface $end)
    {
        $this->start = CarbonImmutable::instance($start)->utc();
        $this->end = CarbonImmutable::instance($end)->utc();

        if ($this->end <= $this->start) {
            throw new InvalidArgumentException('A time range must end after it starts.');
        }
    }

    public function durationMinutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function contains(self $other): bool
    {
        return $this->start <= $other->start && $this->end >= $other->end;
    }

    /**
     * Grow the range outwards, used to apply buffer time around an appointment.
     */
    public function expandedBy(int $minutes): self
    {
        if ($minutes <= 0) {
            return $this;
        }

        return new self(
            $this->start->subMinutes($minutes),
            $this->end->addMinutes($minutes),
        );
    }

    /**
     * Remove another range from this one.
     *
     * Returns zero ranges when fully covered, one when trimmed at an edge, and
     * two when the block falls in the middle and splits the range in half.
     *
     * @return list<self>
     */
    public function subtract(self $block): array
    {
        if (! $this->overlaps($block)) {
            return [$this];
        }

        if ($block->contains($this)) {
            return [];
        }

        $pieces = [];

        if ($block->start > $this->start) {
            $pieces[] = new self($this->start, $block->start);
        }

        if ($block->end < $this->end) {
            $pieces[] = new self($block->end, $this->end);
        }

        return $pieces;
    }

    /**
     * The part shared by both ranges, or null when they do not overlap.
     */
    public function intersect(self $other): ?self
    {
        if (! $this->overlaps($other)) {
            return null;
        }

        return new self(
            $this->start > $other->start ? $this->start : $other->start,
            $this->end < $other->end ? $this->end : $other->end,
        );
    }

    /**
     * Remove every block from every range.
     *
     * @param  list<self>  $ranges
     * @param  list<self>  $blocks
     * @return list<self>
     */
    public static function subtractAll(array $ranges, array $blocks): array
    {
        foreach ($blocks as $block) {
            $next = [];

            foreach ($ranges as $range) {
                foreach ($range->subtract($block) as $piece) {
                    $next[] = $piece;
                }
            }

            $ranges = $next;
        }

        return array_values($ranges);
    }

    /**
     * Every overlap between two sets of ranges, such as the hours where the
     * salon is open and the staff member is also working.
     *
     * @param  list<self>  $left
     * @param  list<self>  $right
     * @return list<self>
     */
    public static function intersectAll(array $left, array $right): array
    {
        $result = [];

        foreach ($left as $a) {
            foreach ($right as $b) {
                $overlap = $a->intersect($b);

                if ($overlap !== null) {
                    $result[] = $overlap;
                }
            }
        }

        return $result;
    }

    public function equalTo(self $other): bool
    {
        return $this->start->equalTo($other->start) && $this->end->equalTo($other->end);
    }

    public function __toString(): string
    {
        return $this->start->toIso8601String().' - '.$this->end->toIso8601String();
    }
}
