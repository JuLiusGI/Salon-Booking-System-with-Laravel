<?php

namespace Tests\Feature\Booking;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\Booking\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

/**
 * The concurrent booking case required by MASTER_SPEC section 23.
 *
 * A genuinely parallel test needs two connections holding open transactions,
 * which the suite cannot do while RefreshDatabase wraps each test in one. What
 * these tests prove instead is everything that determines the outcome of a race:
 * the lock is taken, it is taken before the check, the check re-reads state
 * written by whoever committed first, and the loser writes nothing.
 */
class ConcurrentBookingTest extends TestCase
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

    public function test_the_second_customer_to_reach_the_same_slot_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        $booking = app(BookingService::class);

        // Both customers hold the same slot, exactly as two browsers would.
        $first = $booking->book(User::factory()->create(), $staff, collect([$service]), $slot->startsAt);

        $this->assertNotNull($first->id);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('booked while you were choosing');

        $booking->book(User::factory()->create(), $staff, collect([$service]), $slot->startsAt);
    }

    public function test_the_losing_booking_leaves_no_trace(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        $booking = app(BookingService::class);
        $booking->book(User::factory()->create(), $staff, collect([$service]), $slot->startsAt);

        try {
            $booking->book(User::factory()->create(), $staff, collect([$service]), $slot->startsAt);
            $this->fail('The second booking should have been refused.');
        } catch (BookingException) {
            // Expected.
        }

        // Exactly one appointment and one set of items: the refused attempt must
        // not have written a half-built appointment before failing.
        $this->assertSame(1, Appointment::query()->count());
        $this->assertDatabaseCount('appointment_items', 1);
        $this->assertSame(0, DB::transactionLevel() - 1, 'The failed booking must not leave a transaction open.');
    }

    public function test_booking_takes_the_lock_before_it_checks_for_conflicts(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        DB::enableQueryLog();

        app(BookingService::class)->book(
            User::factory()->create(),
            $staff,
            collect([$service]),
            $slot->startsAt,
        );

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $lockAt = $queries->search(fn (string $q) => str_contains(strtolower($q), 'for update'));
        $insertAt = $queries->search(fn (string $q) => str_starts_with(strtolower($q), 'insert into `appointments`'));

        $this->assertNotFalse($lockAt, 'Booking must take a lock.');
        $this->assertNotFalse($insertAt, 'Booking must insert an appointment.');

        // Checking after the lock is the entire protection. Checking before it
        // would leave the window where both requests pass.
        $this->assertLessThan($insertAt, $lockAt, 'The lock must be taken before the appointment is written.');

        $conflictCheckAt = $queries->search(
            fn (string $q) => str_contains(strtolower($q), 'from `appointments`')
                && str_contains(strtolower($q), 'starts_at')
        );

        $this->assertNotFalse($conflictCheckAt, 'Booking must re-check for conflicts.');
        $this->assertLessThan($conflictCheckAt, $lockAt, 'The conflict check must happen after the lock.');
    }

    public function test_a_slot_that_became_blocked_after_it_was_offered_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        // The admin blocks the time while the customer is still deciding. This
        // is not an appointment conflict, so only the full engine re-run catches
        // it, which is why booking revalidates rather than only checking clashes.
        $this->blockStaff(
            $staff,
            $slot->startsAt->setTimezone($this->salonTimezone())->format('Y-m-d H:i'),
            $slot->endsAt->setTimezone($this->salonTimezone())->format('Y-m-d H:i'),
        );

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('no longer available');

        app(BookingService::class)->book(
            User::factory()->create(),
            $staff,
            collect([$service]),
            $slot->startsAt,
        );
    }

    public function test_a_stylist_deactivated_after_the_form_loaded_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        // Deactivated in the database only, so the in-memory model the caller
        // holds is stale. Booking must re-read inside the lock.
        DB::table('staff')->where('id', $staff->id)->update(['is_bookable' => false]);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('no longer taking bookings');

        app(BookingService::class)->book(
            User::factory()->create(),
            $staff,
            collect([$service]),
            $slot->startsAt,
        );
    }

    public function test_two_customers_booking_different_stylists_do_not_block_each_other(): void
    {
        $first = $this->rosteredStylist();
        $second = $this->rosteredStylist();

        $service = $this->serviceFor($first, 60);
        $service->staff()->attach($second);

        $slot = app(AvailabilityService::class)
            ->slotsFor($first, collect([$service]), $this->localDate(self::DATE))
            ->first();

        $booking = app(BookingService::class);

        $booking->book(User::factory()->create(), $first, collect([$service]), $slot->startsAt);
        $booking->book(User::factory()->create(), $second, collect([$service]), $slot->startsAt);

        // Locking the stylist rather than the time range is what makes this
        // possible: two chairs, same hour, no contention.
        $this->assertSame(2, Appointment::query()->count());
    }
}
