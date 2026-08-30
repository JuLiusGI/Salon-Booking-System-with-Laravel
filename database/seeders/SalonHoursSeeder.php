<?php

namespace Database\Seeders;

use App\Models\SalonHour;
use Illuminate\Database\Seeder;

class SalonHoursSeeder extends Seeder
{
    public function run(): void
    {
        // 0 = Sunday .. 6 = Saturday
        $hours = [
            0 => ['opens_at' => null, 'closes_at' => null, 'is_closed' => true],
            1 => ['opens_at' => '09:00:00', 'closes_at' => '18:00:00', 'is_closed' => false],
            2 => ['opens_at' => '09:00:00', 'closes_at' => '18:00:00', 'is_closed' => false],
            3 => ['opens_at' => '09:00:00', 'closes_at' => '18:00:00', 'is_closed' => false],
            4 => ['opens_at' => '09:00:00', 'closes_at' => '20:00:00', 'is_closed' => false],
            5 => ['opens_at' => '09:00:00', 'closes_at' => '20:00:00', 'is_closed' => false],
            6 => ['opens_at' => '08:00:00', 'closes_at' => '19:00:00', 'is_closed' => false],
        ];

        foreach ($hours as $day => $attributes) {
            SalonHour::updateOrCreate(['day_of_week' => $day], $attributes);
        }
    }
}
