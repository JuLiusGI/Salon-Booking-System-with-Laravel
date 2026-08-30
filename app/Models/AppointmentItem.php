<?php

namespace App\Models;

use Database\Factories\AppointmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentItem extends Model
{
    /** @use HasFactory<AppointmentItemFactory> */
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'service_id',
        'service_name',
        'service_price',
        'service_duration_minutes',
        'staff_id',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_price' => 'decimal:2',
            'service_duration_minutes' => 'integer',
            'position' => 'integer',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * The service as it exists today. May be null if the service was removed;
     * the snapshot columns on this row remain the historical source of truth.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Build an item from a service, capturing its current name, price, and
     * duration so later edits to the service cannot rewrite booking history.
     */
    public static function fromService(Service $service, int $position = 0): self
    {
        return new self([
            'service_id' => $service->getKey(),
            'service_name' => $service->name,
            'service_price' => $service->price,
            'service_duration_minutes' => $service->duration_minutes,
            'position' => $position,
        ]);
    }
}
