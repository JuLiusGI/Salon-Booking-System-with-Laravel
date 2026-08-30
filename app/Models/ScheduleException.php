<?php

namespace App\Models;

use App\Enums\ScheduleExceptionType;
use Database\Factories\ScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleException extends Model
{
    /** @use HasFactory<ScheduleExceptionFactory> */
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'type',
        'starts_at',
        'ends_at',
        'override_opens_at',
        'override_closes_at',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ScheduleExceptionType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Exceptions that apply to the whole salon rather than one staff member.
     *
     * @param  Builder<ScheduleException>  $query
     */
    public function scopeSalonWide(Builder $query): void
    {
        $query->whereNull('staff_id');
    }

    /**
     * Exceptions overlapping the given window, using half-open comparison so
     * back-to-back periods do not count as overlapping.
     *
     * @param  Builder<ScheduleException>  $query
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }
}
