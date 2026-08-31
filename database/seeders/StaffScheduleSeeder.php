<?php

namespace Database\Seeders;

use App\Enums\ScheduleExceptionType;
use App\Models\ScheduleException;
use App\Models\Staff;
use App\Models\StaffAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class StaffScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // Wall-clock times mean nothing without a timezone. Building them in the
        // salon's own zone is what keeps seeded data consistent with the opening
        // hours it ships alongside.
        $timezone = config('salon.timezone');

        foreach (Staff::query()->active()->get() as $staff) {
            // Monday through Saturday, matching the salon being closed Sundays.
            foreach ([1, 2, 3, 4, 5, 6] as $day) {
                StaffAvailability::create([
                    'staff_id' => $staff->id,
                    'day_of_week' => $day,
                    'starts_at' => '09:00:00',
                    'ends_at' => '17:00:00',
                    'is_active' => true,
                ]);

                // Daily lunch break, expressed as a recurring-style exception on
                // the next occurrence of that weekday.
                $date = CarbonImmutable::now($timezone)->startOfWeek()->addDays($day - 1);

                ScheduleException::create([
                    'staff_id' => $staff->id,
                    'type' => ScheduleExceptionType::Break,
                    'starts_at' => $date->setTime(12, 0)->utc(),
                    'ends_at' => $date->setTime(13, 0)->utc(),
                    'reason' => 'Lunch break',
                ]);
            }
        }

        // A salon-wide holiday and one staff leave period, so the availability
        // engine has both shapes to work against in development.
        ScheduleException::create([
            'staff_id' => null,
            'type' => ScheduleExceptionType::Holiday,
            'starts_at' => CarbonImmutable::now($timezone)->addDays(20)->startOfDay()->utc(),
            'ends_at' => CarbonImmutable::now($timezone)->addDays(20)->endOfDay()->utc(),
            'reason' => 'Public holiday',
        ]);

        $onLeave = Staff::query()->active()->first();

        if ($onLeave) {
            ScheduleException::create([
                'staff_id' => $onLeave->id,
                'type' => ScheduleExceptionType::Leave,
                'starts_at' => CarbonImmutable::now($timezone)->addDays(10)->startOfDay()->utc(),
                'ends_at' => CarbonImmutable::now($timezone)->addDays(12)->endOfDay()->utc(),
                'reason' => 'Annual leave',
            ]);
        }
    }
}
