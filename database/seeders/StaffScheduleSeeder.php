<?php

namespace Database\Seeders;

use App\Enums\ScheduleExceptionType;
use App\Models\ScheduleException;
use App\Models\Staff;
use App\Models\StaffAvailability;
use Illuminate\Database\Seeder;

class StaffScheduleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Staff::query()->active()->get() as $staff) {
            // Tuesday through Saturday, matching the salon being closed Sundays.
            foreach ([2, 3, 4, 5, 6] as $day) {
                StaffAvailability::create([
                    'staff_id' => $staff->id,
                    'day_of_week' => $day,
                    'starts_at' => '09:00:00',
                    'ends_at' => '17:00:00',
                    'is_active' => true,
                ]);

                // Daily lunch break, expressed as a recurring-style exception on
                // the next occurrence of that weekday.
                $date = now()->startOfWeek()->addDays($day - 1);

                ScheduleException::create([
                    'staff_id' => $staff->id,
                    'type' => ScheduleExceptionType::Break,
                    'starts_at' => $date->copy()->setTime(12, 0),
                    'ends_at' => $date->copy()->setTime(13, 0),
                    'reason' => 'Lunch break',
                ]);
            }
        }

        // A salon-wide holiday and one staff leave period, so the availability
        // engine has both shapes to work against in development.
        ScheduleException::create([
            'staff_id' => null,
            'type' => ScheduleExceptionType::Holiday,
            'starts_at' => now()->addDays(20)->startOfDay(),
            'ends_at' => now()->addDays(20)->endOfDay(),
            'reason' => 'Public holiday',
        ]);

        $onLeave = Staff::query()->active()->first();

        if ($onLeave) {
            ScheduleException::create([
                'staff_id' => $onLeave->id,
                'type' => ScheduleExceptionType::Leave,
                'starts_at' => now()->addDays(10)->startOfDay(),
                'ends_at' => now()->addDays(12)->endOfDay(),
                'reason' => 'Annual leave',
            ]);
        }
    }
}
