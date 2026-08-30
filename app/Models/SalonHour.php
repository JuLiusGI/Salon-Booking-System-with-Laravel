<?php

namespace App\Models;

use Database\Factories\SalonHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalonHour extends Model
{
    /** @use HasFactory<SalonHourFactory> */
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_closed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }
}
