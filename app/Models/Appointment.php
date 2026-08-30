<?php

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * `status` is deliberately excluded. Status changes must go through the
     * transition rules on AppointmentStatus rather than mass assignment
     * (MASTER_SPEC section 9).
     *
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'qr_token',
        'customer_id',
        'staff_id',
        'starts_at',
        'ends_at',
        'source',
        'total_duration_minutes',
        'total_price',
        'notes',
        'internal_notes',
        'booked_by_id',
        'rescheduled_from_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'total_duration_minutes' => 'integer',
            'total_price' => 'decimal:2',
            'checked_in_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * The QR token is an opaque lookup key and must never be exposed in listings
     * or shared Inertia props (MASTER_SPEC section 20).
     *
     * @var list<string>
     */
    protected $hidden = [
        'qr_token',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppointmentItem::class)->orderBy('position');
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    /**
     * A short, human-quotable reference containing no customer information.
     */
    public static function generateReference(): string
    {
        return 'SB-'.strtoupper(Str::random(10));
    }

    /**
     * An opaque QR payload. Random rather than derived so a token can never be
     * guessed from, or reversed into, appointment or customer details.
     */
    public static function generateQrToken(): string
    {
        return Str::random(64);
    }

    /**
     * Appointments that still occupy time and therefore block availability.
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $blocking = array_values(array_filter(
            AppointmentStatus::cases(),
            fn (AppointmentStatus $status) => $status->blocksAvailability(),
        ));

        $query->whereIn('status', $blocking);
    }

    /**
     * Appointments overlapping the given window.
     *
     * Comparison is half-open so an appointment ending exactly when another
     * starts is not treated as a conflict.
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    /** @param Builder<Appointment> $query */
    public function scopeForStaff(Builder $query, Staff $staff): void
    {
        $query->where('staff_id', $staff->getKey());
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
