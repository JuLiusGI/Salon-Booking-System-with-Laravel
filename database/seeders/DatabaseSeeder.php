<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Order matters: users must exist before staff schedules, services before
     * appointments, and so on.
     */
    public function run(): void
    {
        $this->call([
            SalonHoursSeeder::class,
            BookingRuleSeeder::class,
            UserSeeder::class,
            ServiceCatalogSeeder::class,
            StaffScheduleSeeder::class,
            GallerySeeder::class,
            AppointmentSeeder::class,
        ]);
    }
}
