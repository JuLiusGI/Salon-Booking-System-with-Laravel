<?php

namespace App\Models;

use Database\Factories\StaffAvailabilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAvailability extends Model
{
    /** @use HasFactory<StaffAvailabilityFactory> */
    use HasFactory;

    protected $table = 'staff_availabilities';

    protected $fillable = [
        'staff_id',
        'day_of_week',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** @param Builder<StaffAvailability> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
