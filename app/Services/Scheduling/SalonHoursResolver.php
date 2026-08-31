<?php

namespace App\Services\Scheduling;

use App\Enums\ScheduleExceptionType;
use App\Models\SalonHour;
use App\Models\ScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out when the salon is open on a given day.
 *
 * Opening hours are stored as wall-clock times because that is how a salon
 * thinks: "we open at nine" stays nine o'clock whether or not the clocks have
 * changed. This class is where that wall clock is anchored to a real date in the
 * salon's timezone and converted to UTC instants, which is the only form the rest
 * of the engine ever compares.
 */
class SalonHoursResolver
{
    /**
     * The salon's open period(s) for a salon-local date.
     *
     * Returns an empty list when the salon is closed, whether that is the normal
     * weekly pattern or a one-off holiday or closure.
     *
     * @return list<TimeRange>
     */
    public function openRangesFor(CarbonImmutable $localDate): array
    {
        $dayStart = $this->localMidnight($localDate);

        $special = $this->specialHoursFor($dayStart);

        $ranges = $special !== null
            ? $this->rangeFromTimes($dayStart, $special->override_opens_at, $special->override_closes_at)
            : $this->regularHoursFor($dayStart);

        if ($ranges === []) {
            return [];
        }

        // Holidays and closures cut into whatever hours would otherwise apply.
        return TimeRange::subtractAll($ranges, $this->salonWideClosures($dayStart));
    }

    public function isOpenOn(CarbonImmutable $localDate): bool
    {
        return $this->openRangesFor($localDate) !== [];
    }

    /**
     * @return list<TimeRange>
     */
    private function regularHoursFor(CarbonImmutable $dayStart): array
    {
        /** @var SalonHour|null $hours */
        $hours = SalonHour::query()->where('day_of_week', $dayStart->dayOfWeek)->first();

        if ($hours === null || $hours->is_closed) {
            return [];
        }

        return $this->rangeFromTimes($dayStart, $hours->opens_at, $hours->closes_at);
    }

    /**
     * A salon-wide special-hours exception replaces the normal pattern for the
     * day rather than blocking time out of it.
     */
    private function specialHoursFor(CarbonImmutable $dayStart): ?ScheduleException
    {
        return ScheduleException::query()
            ->salonWide()
            ->where('type', ScheduleExceptionType::SpecialHours)
            ->overlapping($dayStart->utc(), $dayStart->addDay()->utc())
            ->whereNotNull('override_opens_at')
            ->whereNotNull('override_closes_at')
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * @return list<TimeRange>
     */
    private function salonWideClosures(CarbonImmutable $dayStart): array
    {
        $blocking = collect(ScheduleExceptionType::cases())
            ->filter(fn (ScheduleExceptionType $type) => $type->blocksTime() && $type->isSalonWide())
            ->values();

        return ScheduleException::query()
            ->salonWide()
            ->whereIn('type', $blocking)
            ->overlapping($dayStart->utc(), $dayStart->addDay()->utc())
            ->get()
            ->map(fn (ScheduleException $exception) => new TimeRange($exception->starts_at, $exception->ends_at))
            ->all();
    }

    /**
     * Anchor two wall-clock times to a date and convert to UTC instants.
     *
     * @return list<TimeRange>
     */
    private function rangeFromTimes(CarbonImmutable $dayStart, ?string $opensAt, ?string $closesAt): array
    {
        if ($opensAt === null || $closesAt === null) {
            return [];
        }

        $open = $dayStart->setTimeFromTimeString($opensAt);
        $close = $dayStart->setTimeFromTimeString($closesAt);

        // A closing time at or before opening is meaningless rather than an
        // overnight shift; the salon does not trade past midnight.
        if ($close <= $open) {
            return [];
        }

        return [new TimeRange($open->utc(), $close->utc())];
    }

    private function localMidnight(CarbonImmutable $date): CarbonImmutable
    {
        return $date->setTimezone(config('salon.timezone'))->startOfDay();
    }

    /**
     * Every published opening hour row, Monday first, for display.
     *
     * @return Collection<int, SalonHour>
     */
    public function weeklyPattern(): Collection
    {
        return SalonHour::query()
            ->orderByRaw('CASE WHEN day_of_week = 0 THEN 7 ELSE day_of_week END')
            ->get();
    }
}
