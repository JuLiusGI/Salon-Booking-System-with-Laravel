<?php

namespace Tests\Feature\Availability;

use App\Models\Appointment;
use App\Services\Booking\ConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

/**
 * Covers the conflict check and the lock that makes it trustworthy
 * (MASTER_SPEC section 8).
 */
class ConflictDetectionTest extends TestCase
{
    use BuildsSalonSchedule, RefreshDatabase;

    private const DATE = '2026-09-15';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo($this->local('2026-09-01 08:00'));
        $this->openSalon();
        $this->bookingRules();
    }

    private function detector(): ConflictDetector
    {
        return app(ConflictDetector::class);
    }

    public function test_an_overlapping_appointment_is_a_conflict(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $this->assertTrue($this->detector()->hasConflict(
            $staff,
            $this->local(self::DATE.' 11:30'),
            $this->local(self::DATE.' 12:30'),
        ));
    }

    public function test_a_booking_that_starts_exactly_when_another_ends_is_not_a_conflict(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $this->assertFalse($this->detector()->hasConflict(
            $staff,
            $this->local(self::DATE.' 12:00'),
            $this->local(self::DATE.' 13:00'),
        ));
    }

    public function test_the_buffer_turns_a_back_to_back_booking_into_a_conflict(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $this->assertTrue($this->detector()->hasConflict(
            $staff,
            $this->local(self::DATE.' 12:00'),
            $this->local(self::DATE.' 13:00'),
            bufferMinutes: 15,
        ));
    }

    public function test_a_cancelled_appointment_is_not_a_conflict(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->cancelled()
            ->create();

        $this->assertFalse($this->detector()->hasConflict(
            $staff,
            $this->local(self::DATE.' 11:00'),
            $this->local(self::DATE.' 12:00'),
        ));
    }

    public function test_another_stylists_booking_is_not_a_conflict(): void
    {
        $staff = $this->rosteredStylist();
        $other = $this->rosteredStylist();

        Appointment::factory()->forStaff($other)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $this->assertFalse($this->detector()->hasConflict(
            $staff,
            $this->local(self::DATE.' 11:00'),
            $this->local(self::DATE.' 12:00'),
        ));
    }

    public function test_the_appointment_being_rescheduled_is_not_its_own_conflict(): void
    {
        $staff = $this->rosteredStylist();

        $existing = Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $this->assertFalse($this->detector()->hasConflict(
            $staff,
            $this->local(self::DATE.' 11:00'),
            $this->local(self::DATE.' 12:00'),
            ignoreAppointmentId: $existing->id,
        ));
    }

    public function test_the_conflicting_appointments_are_reported_for_a_useful_error(): void
    {
        $staff = $this->rosteredStylist();

        $clash = Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        $found = $this->detector()->conflictingAppointments(
            $staff,
            $this->local(self::DATE.' 11:30'),
            $this->local(self::DATE.' 12:30'),
        );

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($clash));
    }

    /* The lock ------------------------------------------------------------- */

    public function test_the_lock_refuses_to_run_outside_a_transaction(): void
    {
        $staff = $this->rosteredStylist();

        // Without a surrounding transaction the lock is released the instant the
        // select finishes, which would give false confidence rather than safety.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must run inside a transaction');

        // RefreshDatabase wraps tests in a transaction, so unwind it first.
        DB::rollBack();

        try {
            $this->detector()->withStaffLocked($staff, fn () => null);
        } finally {
            DB::beginTransaction();
        }
    }

    public function test_the_lock_issues_a_select_for_update_on_the_staff_row(): void
    {
        $staff = $this->rosteredStylist();

        DB::enableQueryLog();

        DB::transaction(function () use ($staff) {
            $this->detector()->withStaffLocked($staff, fn () => true);
        });

        $statements = collect(DB::getQueryLog())->pluck('query')->implode(' | ');

        DB::disableQueryLog();

        $this->assertStringContainsStringIgnoringCase('for update', $statements);
        $this->assertStringContainsStringIgnoringCase('from `staff`', $statements);
    }

    public function test_the_lock_returns_the_result_of_the_work_it_wraps(): void
    {
        $staff = $this->rosteredStylist();

        $result = DB::transaction(fn () => $this->detector()->withStaffLocked($staff, fn () => 'booked'));

        $this->assertSame('booked', $result);
    }

    public function test_a_second_booking_is_rejected_once_the_first_has_been_written(): void
    {
        $staff = $this->rosteredStylist();
        $start = $this->local(self::DATE.' 11:00');
        $end = $this->local(self::DATE.' 12:00');

        // This is the sequential half of the race: whichever request commits
        // first wins, and the second must see it and refuse. Proving that two
        // truly parallel requests serialise needs a second connection outside
        // the test transaction, which the suite cannot hold open.
        $booked = DB::transaction(function () use ($staff, $start, $end) {
            return $this->detector()->withStaffLocked($staff, function () use ($staff, $start, $end) {
                if ($this->detector()->hasConflict($staff, $start, $end)) {
                    return false;
                }

                Appointment::factory()->forStaff($staff)->at($start, 60)->create();

                return true;
            });
        });

        $second = DB::transaction(function () use ($staff, $start, $end) {
            return $this->detector()->withStaffLocked($staff, function () use ($staff, $start, $end) {
                return ! $this->detector()->hasConflict($staff, $start, $end);
            });
        });

        $this->assertTrue($booked);
        $this->assertFalse($second, 'The second booking must see the first and refuse.');
        $this->assertSame(1, Appointment::query()->where('staff_id', $staff->id)->count());
    }
}
