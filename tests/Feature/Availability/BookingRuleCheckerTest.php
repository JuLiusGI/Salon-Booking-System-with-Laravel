<?php

namespace Tests\Feature\Availability;

use App\Models\Appointment;
use App\Services\Booking\BookingRuleChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

class BookingRuleCheckerTest extends TestCase
{
    use BuildsSalonSchedule, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo($this->local('2026-09-01 08:00'));
    }

    public function test_the_defaults_are_used_when_no_rules_row_exists(): void
    {
        $checker = new BookingRuleChecker;

        $defaults = config('salon.booking_rule_defaults');

        $this->assertSame($defaults['slot_interval_minutes'], $checker->slotIntervalMinutes());
        $this->assertSame($defaults['buffer_minutes'], $checker->bufferMinutes());
    }

    public function test_a_start_time_before_the_minimum_notice_is_refused(): void
    {
        $checker = new BookingRuleChecker($this->bookingRules(['min_advance_minutes' => 120]));

        $this->assertFalse($checker->allowsStartAt($this->local('2026-09-01 09:00')));
        $this->assertTrue($checker->allowsStartAt($this->local('2026-09-01 10:30')));
    }

    public function test_a_start_time_beyond_the_horizon_is_refused(): void
    {
        $checker = new BookingRuleChecker($this->bookingRules(['max_advance_days' => 7]));

        $this->assertTrue($checker->allowsStartAt($this->local('2026-09-05 10:00')));
        $this->assertFalse($checker->allowsStartAt($this->local('2026-09-20 10:00')));
    }

    public function test_a_duration_beyond_the_maximum_is_refused(): void
    {
        $checker = new BookingRuleChecker($this->bookingRules(['max_duration_minutes' => 120]));

        $this->assertTrue($checker->allowsDuration(120));
        $this->assertFalse($checker->allowsDuration(121));
        $this->assertFalse($checker->allowsDuration(0));
    }

    public function test_no_maximum_duration_means_any_positive_length_is_allowed(): void
    {
        $checker = new BookingRuleChecker($this->bookingRules(['max_duration_minutes' => null]));

        $this->assertTrue($checker->allowsDuration(600));
        $this->assertFalse($checker->allowsDuration(-10));
    }

    public function test_cancellation_is_allowed_before_the_deadline_and_refused_after(): void
    {
        $checker = new BookingRuleChecker($this->bookingRules(['cancellation_deadline_hours' => 24]));

        $appointment = Appointment::factory()->at($this->local('2026-09-10 14:00'), 60)->create();

        $this->travelTo($this->local('2026-09-09 10:00'));
        $this->assertTrue($checker->allowsCancellation($appointment));

        // Now inside the 24 hour window before the appointment.
        $this->travelTo($this->local('2026-09-09 15:00'));
        $this->assertFalse($checker->allowsCancellation($appointment));
    }

    public function test_rescheduling_has_its_own_deadline(): void
    {
        $checker = new BookingRuleChecker($this->bookingRules([
            'cancellation_deadline_hours' => 2,
            'reschedule_deadline_hours' => 48,
        ]));

        $appointment = Appointment::factory()->at($this->local('2026-09-10 14:00'), 60)->create();

        $this->travelTo($this->local('2026-09-09 10:00'));

        // Cancelling is still fine this close, but rescheduling is not.
        $this->assertTrue($checker->allowsCancellation($appointment));
        $this->assertFalse($checker->allowsRescheduling($appointment));
    }

    public function test_the_deadlines_are_reported_so_they_can_be_shown_to_the_customer(): void
    {
        $checker = new BookingRuleChecker($this->bookingRules([
            'cancellation_deadline_hours' => 24,
            'reschedule_deadline_hours' => 12,
        ]));

        $appointment = Appointment::factory()->at($this->local('2026-09-10 14:00'), 60)->create();

        $this->assertTrue(
            $checker->cancellationDeadlineFor($appointment)->equalTo($this->local('2026-09-09 14:00'))
        );
        $this->assertTrue(
            $checker->reschedulingDeadlineFor($appointment)->equalTo($this->local('2026-09-10 02:00'))
        );
    }

    public function test_a_slot_interval_is_never_smaller_than_five_minutes(): void
    {
        $checker = new BookingRuleChecker($this->bookingRules(['slot_interval_minutes' => 0]));

        $this->assertSame(5, $checker->slotIntervalMinutes());
    }
}
