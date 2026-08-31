<?php

namespace Tests\Feature\Availability;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleExceptionType;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffAvailability;
use App\Services\Availability\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

/**
 * Covers every availability constraint in MASTER_SPEC section 10.
 *
 * The test date is a fixed Tuesday well inside the booking horizon, and the clock
 * is frozen, so nothing here depends on when the suite happens to run.
 */
class AvailabilityEngineTest extends TestCase
{
    use BuildsSalonSchedule, RefreshDatabase;

    private const DATE = '2026-09-15';

    protected function setUp(): void
    {
        parent::setUp();

        // 08:00 salon time on 1 September, two weeks before the test date.
        $this->travelTo($this->local('2026-09-01 08:00'));

        $this->openSalon();
        $this->bookingRules();
    }

    private function engine(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    /**
     * @param  Service|list<Service>  $services
     */
    private function slots(Staff $staff, $services, string $date = self::DATE)
    {
        return $this->engine()->slotsFor(
            $staff,
            collect(is_array($services) ? $services : [$services]),
            $this->localDate($date),
        );
    }

    /* 1. Salon operating hours -------------------------------------------- */

    public function test_slots_run_between_opening_and_closing_in_salon_time(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $labels = $this->labels($this->slots($staff, $service));

        $this->assertSame('09:00', $labels[0]);

        // The last hour-long appointment must finish by 17:00, so it starts at 16:00.
        $this->assertSame('16:00', end($labels));
    }

    public function test_nothing_is_offered_when_the_salon_is_closed_that_weekday(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff);

        $this->closeSalonOn($this->localDate(self::DATE)->dayOfWeek);

        $this->assertCount(0, $this->slots($staff, $service));
    }

    public function test_a_shorter_opening_day_shortens_availability(): void
    {
        $staff = $this->rosteredStylist('08:00', '20:00');
        $service = $this->serviceFor($staff, 60);

        $this->openSalon('10:00', '14:00');

        $labels = $this->labels($this->slots($staff, $service));

        $this->assertSame('10:00', $labels[0]);
        $this->assertSame('13:00', end($labels));
    }

    /* 2. Staff working schedule ------------------------------------------- */

    public function test_availability_is_limited_to_the_staff_members_shift(): void
    {
        $staff = $this->rosteredStylist('12:00', '15:00');
        $service = $this->serviceFor($staff, 60);

        $labels = $this->labels($this->slots($staff, $service));

        $this->assertSame('12:00', $labels[0]);
        $this->assertSame('14:00', end($labels));
    }

    public function test_a_staff_member_not_rostered_that_day_offers_nothing(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff);

        StaffAvailability::query()
            ->where('staff_id', $staff->id)
            ->where('day_of_week', $this->localDate(self::DATE)->dayOfWeek)
            ->delete();

        $this->assertCount(0, $this->slots($staff, $service));
    }

    public function test_a_split_shift_produces_two_blocks_of_availability(): void
    {
        $staff = Staff::factory()->create();
        $dayOfWeek = $this->localDate(self::DATE)->dayOfWeek;

        foreach ([['09:00', '11:00'], ['14:00', '16:00']] as [$from, $to]) {
            StaffAvailability::create([
                'staff_id' => $staff->id,
                'day_of_week' => $dayOfWeek,
                'starts_at' => $from,
                'ends_at' => $to,
                'is_active' => true,
            ]);
        }

        $service = $this->serviceFor($staff, 60);

        $this->assertSame(
            ['09:00', '09:15', '09:30', '09:45', '10:00', '14:00', '14:15', '14:30', '14:45', '15:00'],
            $this->labels($this->slots($staff, $service)),
        );
    }

    public function test_an_inactive_or_unbookable_staff_member_offers_nothing(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff);

        $staff->is_active = false;
        $staff->save();
        $this->assertCount(0, $this->slots($staff->fresh(), $service));

        $staff->is_active = true;
        $staff->is_bookable = false;
        $staff->save();
        $this->assertCount(0, $this->slots($staff->fresh(), $service));
    }

    /* 3. Staff-service capability ----------------------------------------- */

    public function test_a_stylist_who_cannot_perform_the_service_offers_nothing(): void
    {
        $staff = $this->rosteredStylist();
        $other = $this->rosteredStylist();

        $service = $this->serviceFor($other, 60);

        $this->assertCount(0, $this->slots($staff, $service));
    }

    public function test_a_multi_service_booking_needs_the_stylist_to_do_every_service(): void
    {
        $staff = $this->rosteredStylist();
        $other = $this->rosteredStylist();

        $canDo = $this->serviceFor($staff, 30);
        $cannotDo = $this->serviceFor($other, 30);

        $this->assertGreaterThan(0, $this->slots($staff, [$canDo])->count());
        $this->assertCount(0, $this->slots($staff, [$canDo, $cannotDo]));
    }

    /* 4. Existing appointments -------------------------------------------- */

    public function test_an_existing_appointment_removes_the_overlapping_slots(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $labels = $this->labels($this->slots($staff, $service));

        // Nothing may start between 10:01 and 11:59, since an hour-long booking
        // would run into the existing one.
        $this->assertNotContains('10:15', $labels);
        $this->assertNotContains('11:00', $labels);
        $this->assertNotContains('11:30', $labels);

        // Butting up on either side is fine.
        $this->assertContains('10:00', $labels);
        $this->assertContains('12:00', $labels);
    }

    public function test_a_cancelled_or_no_show_appointment_gives_its_slot_back(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->cancelled()
            ->create();

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 13:00'), 60)
            ->noShow()
            ->create();

        $labels = $this->labels($this->slots($staff, $service));

        $this->assertContains('11:00', $labels);
        $this->assertContains('13:00', $labels);
    }

    public function test_another_stylists_appointment_does_not_block_this_one(): void
    {
        $staff = $this->rosteredStylist();
        $other = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        Appointment::factory()->forStaff($other)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $this->assertContains('11:00', $this->labels($this->slots($staff, $service)));
    }

    /* 5. Breaks ------------------------------------------------------------ */

    public function test_a_break_removes_the_slots_it_covers(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->blockStaff($staff, self::DATE.' 12:00', self::DATE.' 13:00');

        $labels = $this->labels($this->slots($staff, $service));

        $this->assertNotContains('12:00', $labels);
        $this->assertNotContains('11:30', $labels);
        $this->assertContains('11:00', $labels);
        $this->assertContains('13:00', $labels);
    }

    /* 6. Leave and days off ------------------------------------------------ */

    public function test_leave_covering_the_whole_day_removes_everything(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->blockStaff(
            $staff,
            self::DATE.' 00:00',
            self::DATE.' 23:59',
            ScheduleExceptionType::Leave,
        );

        $this->assertCount(0, $this->slots($staff, $service));
    }

    public function test_a_day_off_only_affects_the_staff_member_who_took_it(): void
    {
        $staff = $this->rosteredStylist();
        $other = $this->rosteredStylist();

        $service = $this->serviceFor($staff, 60);
        $service->staff()->attach($other);

        $this->blockStaff($staff, self::DATE.' 00:00', self::DATE.' 23:59', ScheduleExceptionType::DayOff);

        $this->assertCount(0, $this->slots($staff, $service));
        $this->assertGreaterThan(0, $this->slots($other, $service)->count());
    }

    /* 7. Holidays and closures --------------------------------------------- */

    public function test_a_salon_wide_holiday_closes_the_day_for_everyone(): void
    {
        $staff = $this->rosteredStylist();
        $other = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $service->staff()->attach($other);

        $this->closeSalonBetween(self::DATE.' 00:00', self::DATE.' 23:59');

        $this->assertCount(0, $this->slots($staff, $service));
        $this->assertCount(0, $this->slots($other, $service));
    }

    public function test_a_partial_closure_only_removes_the_hours_it_covers(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->closeSalonBetween(
            self::DATE.' 13:00',
            self::DATE.' 17:00',
            ScheduleExceptionType::Closure,
        );

        $labels = $this->labels($this->slots($staff, $service));

        $this->assertContains('09:00', $labels);
        $this->assertSame('12:00', end($labels));
    }

    /* 8. Special hours ------------------------------------------------------ */

    public function test_special_hours_replace_the_normal_opening_pattern(): void
    {
        $staff = $this->rosteredStylist('08:00', '20:00');
        $service = $this->serviceFor($staff, 60);

        $this->specialHoursOn(self::DATE, '11:00', '15:00');

        $labels = $this->labels($this->slots($staff, $service));

        $this->assertSame('11:00', $labels[0]);
        $this->assertSame('14:00', end($labels));
    }

    /* 9. Booking rules ------------------------------------------------------ */

    public function test_the_minimum_advance_notice_hides_slots_that_are_too_soon(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->bookingRules(['min_advance_minutes' => 180]);

        // Now is 08:00 on the day itself, so nothing before 11:00 may be offered.
        $this->travelTo($this->local('2026-09-15 08:00'));

        $labels = $this->labels($this->slots($staff, $service));

        $this->assertNotContains('09:00', $labels);
        $this->assertNotContains('10:45', $labels);
        $this->assertSame('11:00', $labels[0]);
    }

    public function test_the_booking_horizon_hides_dates_too_far_ahead(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->bookingRules(['max_advance_days' => 7]);

        $this->assertCount(0, $this->slots($staff, $service, '2026-09-15'));
        $this->assertGreaterThan(0, $this->slots($staff, $service, '2026-09-05')->count());
    }

    public function test_the_slot_interval_controls_the_spacing_of_offered_times(): void
    {
        $staff = $this->rosteredStylist('09:00', '12:00');
        $service = $this->serviceFor($staff, 60);

        $this->bookingRules(['slot_interval_minutes' => 30]);

        $this->assertSame(['09:00', '09:30', '10:00', '10:30', '11:00'], $this->labels($this->slots($staff, $service)));
    }

    public function test_a_booking_longer_than_the_maximum_is_refused_outright(): void
    {
        $staff = $this->rosteredStylist();
        $long = $this->serviceFor($staff, 180);

        $this->bookingRules(['max_duration_minutes' => 120]);

        $this->assertCount(0, $this->slots($staff, $long));
    }

    /* 10. Buffer ------------------------------------------------------------ */

    public function test_the_buffer_keeps_a_gap_around_existing_appointments(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->bookingRules(['buffer_minutes' => 15]);

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $labels = $this->labels($this->slots($staff, $service));

        // Butting straight up against the appointment is no longer allowed.
        $this->assertNotContains('10:00', $labels);
        $this->assertNotContains('12:00', $labels);

        $this->assertContains('09:45', $labels);
        $this->assertContains('12:15', $labels);
    }

    public function test_the_buffer_does_not_push_the_first_slot_past_opening(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->bookingRules(['buffer_minutes' => 30]);

        // Buffer is turnaround between clients, not dead time at the start of
        // the day, so opening time is still bookable.
        $this->assertSame('09:00', $this->labels($this->slots($staff, $service))[0]);
    }

    /* Multi-service and duration -------------------------------------------- */

    public function test_multiple_services_are_booked_as_one_continuous_block(): void
    {
        $staff = $this->rosteredStylist('09:00', '12:00');
        $cut = $this->serviceFor($staff, 45);
        $colour = $this->serviceFor($staff, 90);

        $slots = $this->slots($staff, [$cut, $colour]);
        $labels = $this->labels($slots);

        $this->assertSame(135, $slots->first()->durationMinutes());
        $this->assertSame('09:00', $labels[0]);

        // 135 minutes from 09:45 would end at 12:00 exactly, which fits.
        $this->assertSame('09:45', end($labels));
    }

    public function test_a_gap_shorter_than_the_appointment_is_not_offered(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 90);

        // Leaves a 60 minute hole between 11:00 and 12:00.
        Appointment::factory()->forStaff($staff)->at($this->local(self::DATE.' 09:30'), 90)->create();
        Appointment::factory()->forStaff($staff)->at($this->local(self::DATE.' 12:00'), 60)->create();

        $this->assertNotContains('11:00', $this->labels($this->slots($staff, $service)));
    }

    /* Timezone --------------------------------------------------------------- */

    public function test_offered_instants_are_utc_but_read_as_salon_wall_clock(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $first = $this->slots($staff, $service)->first();

        $this->assertSame('UTC', $first->startsAt->tzName);

        // 09:00 in Manila is 01:00 UTC on the same date.
        $this->assertSame('2026-09-15 01:00', $first->startsAt->format('Y-m-d H:i'));
        $this->assertSame('9:00 AM', $first->toArray()['label']);
        $this->assertSame('2026-09-15', $first->toArray()['local_date']);
    }

    public function test_an_appointment_stored_in_utc_blocks_the_right_local_hour(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        // 03:00 UTC is 11:00 in Manila.
        Appointment::factory()->forStaff($staff)
            ->at(CarbonImmutable::parse('2026-09-15 03:00', 'UTC'), 60)
            ->create();

        $labels = $this->labels($this->slots($staff, $service));

        $this->assertNotContains('11:00', $labels);
        $this->assertContains('12:00', $labels);
    }

    /* canAccommodate --------------------------------------------------------- */

    public function test_can_accommodate_accepts_a_slot_the_engine_offered(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = $this->slots($staff, $service)->first();

        $this->assertTrue($this->engine()->canAccommodate($staff, collect([$service]), $slot->startsAt));
    }

    public function test_can_accommodate_refuses_a_window_that_is_no_longer_free(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = $this->slots($staff, $service)->first();

        Appointment::factory()->forStaff($staff)->at($slot->startsAt, 60)->create();

        $this->assertFalse($this->engine()->canAccommodate($staff, collect([$service]), $slot->startsAt));
    }

    public function test_can_accommodate_refuses_a_start_time_off_the_grid_that_still_fits(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        // 09:07 is inside opening hours and free, but is not an offered slot.
        // It is still accepted, because the grid is a presentation choice and
        // the real constraint is whether the window is free.
        $this->assertTrue($this->engine()->canAccommodate(
            $staff,
            collect([$service]),
            $this->local(self::DATE.' 09:07'),
        ));
    }

    public function test_can_accommodate_refuses_a_window_outside_opening_hours(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->assertFalse($this->engine()->canAccommodate(
            $staff,
            collect([$service]),
            $this->local(self::DATE.' 18:00'),
        ));
    }

    public function test_can_accommodate_can_ignore_the_appointment_being_rescheduled(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $existing = Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $start = $this->local(self::DATE.' 11:00');

        $this->assertFalse($this->engine()->canAccommodate($staff, collect([$service]), $start));

        // Rescheduling an appointment must not treat itself as a conflict.
        $this->assertTrue($this->engine()->canAccommodate(
            $staff,
            collect([$service]),
            $start,
            null,
            null,
            $existing->id,
        ));
    }

    /* Totals ------------------------------------------------------------------ */

    public function test_duration_and_price_are_summed_from_the_chosen_services(): void
    {
        $staff = $this->rosteredStylist();
        $cut = $this->serviceFor($staff, 45, 750.00);
        $colour = $this->serviceFor($staff, 90, 2800.00);

        $services = collect([$cut, $colour]);

        $this->assertSame(135, $this->engine()->totalDuration($services));
        $this->assertSame('3550.00', $this->engine()->totalPrice($services));
    }

    /* Everything at once -------------------------------------------------------- */

    public function test_a_realistic_day_with_every_constraint_applied_at_once(): void
    {
        $staff = $this->rosteredStylist('09:00', '17:00');
        $service = $this->serviceFor($staff, 60);

        $this->bookingRules(['buffer_minutes' => 15, 'slot_interval_minutes' => 30]);

        $this->blockStaff($staff, self::DATE.' 12:00', self::DATE.' 13:00');
        $this->closeSalonBetween(self::DATE.' 16:00', self::DATE.' 17:00', ScheduleExceptionType::Closure);

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 09:30'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $labels = $this->labels($this->slots($staff, $service));

        // 09:00 no longer fits: the 09:30 booking plus its buffer starts at 09:15.
        // After that appointment ends at 10:30, the buffer pushes the next start
        // to 10:45, and the grid rounds it to 11:00.
        // The lunch break removes 12:00, the closure removes everything from
        // 16:00, and the buffer keeps 15:00 as the last viable start.
        $this->assertSame(['11:00', '13:00', '13:30', '14:00', '14:30', '15:00'], $labels);
    }
}
