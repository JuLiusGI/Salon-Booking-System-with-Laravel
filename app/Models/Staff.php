<?php

namespace App\Models;

use App\Services\Media\ImageStorage;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Laravel would otherwise pluralise this to "staffs".
     */
    protected $table = 'staff';

    protected $fillable = [
        'title',
        'bio',
        'hired_on',
        'is_active',
        'is_bookable',
        'display_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hired_on' => 'date',
            'is_active' => 'boolean',
            'is_bookable' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_staff')->withTimestamps();
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(StaffAvailability::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Whether this staff member is allowed to perform a given service.
     */
    /**
     * Staff portraits are stored on the user record alongside every other
     * account avatar, so there is only one place a person's photo can live.
     */
    public function photoUrl(): ?string
    {
        return app(ImageStorage::class)->url($this->user?->avatar_path);
    }

    public function canPerform(Service $service): bool
    {
        return $this->services()->whereKey($service->getKey())->exists();
    }

    /** @param Builder<Staff> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Staff> $query */
    public function scopeBookable(Builder $query): void
    {
        $query->where('is_active', true)->where('is_bookable', true);
    }
}
