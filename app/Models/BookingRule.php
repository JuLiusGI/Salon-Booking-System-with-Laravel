<?php

namespace App\Models;

use Database\Factories\BookingRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingRule extends Model
{
    /** @use HasFactory<BookingRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'min_advance_minutes',
        'max_advance_days',
        'cancellation_deadline_hours',
        'reschedule_deadline_hours',
        'buffer_minutes',
        'slot_interval_minutes',
        'max_duration_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_advance_minutes' => 'integer',
            'max_advance_days' => 'integer',
            'cancellation_deadline_hours' => 'integer',
            'reschedule_deadline_hours' => 'integer',
            'buffer_minutes' => 'integer',
            'slot_interval_minutes' => 'integer',
            'max_duration_minutes' => 'integer',
        ];
    }

    /**
     * The single active rule set, falling back to configured defaults if the row
     * has not been created yet.
     */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? new self(config('salon.booking_rule_defaults'));
    }
}
