<?php

namespace App\Enums;

enum ScheduleExceptionType: string
{
    case Leave = 'leave';
    case Holiday = 'holiday';
    case DayOff = 'day_off';
    case Break = 'break';
    case Closure = 'closure';
    case SpecialHours = 'special_hours';

    public function label(): string
    {
        return match ($this) {
            self::Leave => 'Leave',
            self::Holiday => 'Holiday',
            self::DayOff => 'Day Off',
            self::Break => 'Break',
            self::Closure => 'Temporary Closure',
            self::SpecialHours => 'Special Hours',
        };
    }

    /**
     * Whether this exception removes time from the schedule.
     *
     * Every type blocks except SpecialHours, which instead REPLACES the normal
     * opening hours for the affected period using the override columns.
     */
    public function blocksTime(): bool
    {
        return $this !== self::SpecialHours;
    }

    /**
     * Types that apply to the whole salon rather than one staff member.
     */
    public function isSalonWide(): bool
    {
        return in_array($this, [self::Holiday, self::Closure], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
