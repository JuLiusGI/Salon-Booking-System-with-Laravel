<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\ScheduleExceptionType;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

/**
 * Covers the required booking cases in MASTER_SPEC section 23, end to end
 * through the HTTP layer.
 */
class BookingFlowTest extends TestCase
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

    private function customer(): User
    {
        return User::factory()->create();
    }

    /**
     * The first slot the engine actually offers, so tests book something real.
     *
     * @param  list<Service>  $services
     */
    private function firstSlot(Staff $staff, array $services, string $date = self::DATE): CarbonImmutable
    {
        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect($services), $this->localDate($date))
            ->first();

        $this->assertNotNull($slot, 'Expected the engine to offer at least one slot.');

        return $slot->startsAt;
    }

    /**
     * @param  list<Service>  $services
     * @return array<string, mixed>
     */
    private function payload(Staff $staff, array $services, ?CarbonImmutable $startsAt = null): array
    {
        return [
            'service_ids' => collect($services)->pluck('id')->all(),
            'staff_id' => $staff->id,
            'starts_at' => ($startsAt ?? $this->firstSlot($staff, $services))->toIso8601String(),
        ];
    }

    /* Required case: valid single-service booking ---------------------------- */

    public function test_a_customer_can_book_a_single_service(): void
    {
        $customer = $this->customer();
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60, 750.00);

        $response = $this->actingAs($customer)->post('/book/new', $this->payload($staff, [$service]));

        $response->assertSessionHasNoErrors();

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame($customer->id, $appointment->customer_id);
        $this->assertSame($staff->id, $appointment->staff_id);
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame(AppointmentSource::Online, $appointment->source);
        $this->assertSame(60, $appointment->total_duration_minutes);
        $this->assertSame('750.00', $appointment->total_price);

        $response->assertRedirect(route('appointments.show', $appointment->reference));
    }

    public function test_each_booking_gets_a_distinct_reference_and_qr_token(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        // Book the first free slot, then ask again. Consecutive offered slots are
        // only 15 minutes apart and would overlap an hour-long service, so the
        // second booking has to come from freshly recomputed availability.
        foreach ([1, 2] as $ignored) {
            $this->actingAs($this->customer())
                ->post('/book/new', $this->payload($staff, [$service], $this->firstSlot($staff, [$service])))
                ->assertSessionHasNoErrors();
        }

        $appointments = Appointment::query()->get();

        $this->assertCount(2, $appointments);

        foreach ($appointments as $appointment) {
            $this->assertStringStartsWith('SB-', $appointment->reference);
            $this->assertSame(64, strlen($appointment->qr_token));
        }

        // Random, not sequential or derived, so neither can be guessed from
        // another appointment (MASTER_SPEC section 20).
        $this->assertCount(2, $appointments->pluck('reference')->unique());
        $this->assertCount(2, $appointments->pluck('qr_token')->unique());
    }

    /* Required case: valid multi-service booking ------------------------------ */

    public function test_a_customer_can_book_several_services_as_one_appointment(): void
    {
        $staff = $this->rosteredStylist();
        $cut = $this->serviceFor($staff, 45, 750.00);
        $colour = $this->serviceFor($staff, 90, 2800.00);

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$cut, $colour]))
            ->assertSessionHasNoErrors();

        $appointment = Appointment::query()->with('items')->firstOrFail();

        $this->assertCount(2, $appointment->items);
        $this->assertSame(135, $appointment->total_duration_minutes);
        $this->assertSame('3550.00', $appointment->total_price);

        // One continuous block, not two bookings.
        $this->assertSame(
            135,
            (int) $appointment->starts_at->diffInMinutes($appointment->ends_at),
        );
    }

    public function test_appointment_items_snapshot_the_service_as_booked(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60, 750.00);
        $service->update(['name' => 'Haircut & Blow Dry']);

        $this->actingAs($this->customer())->post('/book/new', $this->payload($staff, [$service]));

        $appointment = Appointment::query()->with('items')->firstOrFail();
        $item = $appointment->items->first();

        $this->assertSame('Haircut & Blow Dry', $item->service_name);
        $this->assertSame('750.00', $item->service_price);
        $this->assertSame(60, $item->service_duration_minutes);

        // The salon raises the price afterwards.
        $service->update(['price' => 1200.00, 'duration_minutes' => 90]);

        $item->refresh();

        $this->assertSame('750.00', $item->service_price);
        $this->assertSame(60, $item->service_duration_minutes);
    }

    /* Required case: invalid service/staff combination ------------------------ */

    public function test_a_stylist_who_cannot_perform_the_service_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $other = $this->rosteredStylist();

        $theirs = $this->serviceFor($other, 60);
        $mine = $this->serviceFor($staff, 60);

        $this->actingAs($this->customer())
            ->post('/book/new', [
                'service_ids' => [$theirs->id],
                'staff_id' => $staff->id,
                'starts_at' => $this->firstSlot($staff, [$mine])->toIso8601String(),
            ])
            ->assertSessionHasErrors('staff_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_multi_service_booking_needs_one_stylist_for_all_of_it(): void
    {
        $staff = $this->rosteredStylist();
        $other = $this->rosteredStylist();

        $mine = $this->serviceFor($staff, 30);
        $theirs = $this->serviceFor($other, 30);

        $this->actingAs($this->customer())
            ->post('/book/new', [
                'service_ids' => [$mine->id, $theirs->id],
                'staff_id' => $staff->id,
                'starts_at' => $this->firstSlot($staff, [$mine])->toIso8601String(),
            ])
            ->assertSessionHasErrors('staff_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    /* Required case: outside salon hours -------------------------------------- */

    public function test_a_time_outside_opening_hours_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $this->local(self::DATE.' 21:00')))
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_time_on_a_day_the_salon_is_closed_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $start = $this->firstSlot($staff, [$service]);
        $this->closeSalonOn($this->localDate(self::DATE)->dayOfWeek);

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $start))
            ->assertSessionHasErrors('starts_at');
    }

    /* Required case: staff unavailable ---------------------------------------- */

    public function test_a_stylist_who_is_not_rostered_that_day_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $start = $this->firstSlot($staff, [$service]);
        $staff->availabilities()->delete();

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $start))
            ->assertSessionHasErrors('starts_at');
    }

    public function test_a_deactivated_stylist_cannot_be_booked(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $start = $this->firstSlot($staff, [$service]);

        $staff->is_bookable = false;
        $staff->save();

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $start))
            ->assertSessionHasErrors('staff_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    /* Required case: existing overlapping appointment -------------------------- */

    public function test_an_already_booked_slot_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $start = $this->firstSlot($staff, [$service]);

        Appointment::factory()->forStaff($staff)->at($start, 60)->create();

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $start))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Appointment::query()->count());
    }

    /* Required case: buffer conflict -------------------------------------------- */

    public function test_a_slot_that_breaks_the_buffer_is_refused(): void
    {
        $this->bookingRules(['buffer_minutes' => 15]);

        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->create();

        // 12:00 butts straight up against it, which the 15 minute buffer forbids.
        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $this->local(self::DATE.' 12:00')))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Appointment::query()->count());
    }

    /* Required case: holiday and closure ----------------------------------------- */

    public function test_a_slot_on_a_salon_holiday_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $start = $this->firstSlot($staff, [$service]);
        $this->closeSalonBetween(self::DATE.' 00:00', self::DATE.' 23:59');

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $start))
            ->assertSessionHasErrors('starts_at');
    }

    /* Required case: break conflict ------------------------------------------------ */

    public function test_a_slot_during_a_break_is_refused(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->blockStaff($staff, self::DATE.' 12:00', self::DATE.' 13:00');

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $this->local(self::DATE.' 12:00')))
            ->assertSessionHasErrors('starts_at');
    }

    public function test_leave_blocks_the_whole_day(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $start = $this->firstSlot($staff, [$service]);

        $this->blockStaff(
            $staff,
            self::DATE.' 00:00',
            self::DATE.' 23:59',
            ScheduleExceptionType::Leave,
        );

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $start))
            ->assertSessionHasErrors('starts_at');
    }

    /* Required case: booking horizon and minimum notice ------------------------------ */

    public function test_a_date_beyond_the_booking_horizon_is_refused(): void
    {
        $this->bookingRules(['max_advance_days' => 7]);

        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $this->local('2026-10-20 10:00')))
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_time_inside_the_minimum_notice_is_refused(): void
    {
        $this->bookingRules(['min_advance_minutes' => 180]);

        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $this->travelTo($this->local(self::DATE.' 09:00'));

        // 10:00 is only an hour away, inside the three hour notice period.
        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $this->local(self::DATE.' 10:00')))
            ->assertSessionHasErrors('starts_at');
    }

    public function test_an_appointment_longer_than_the_maximum_is_refused(): void
    {
        $this->bookingRules(['max_duration_minutes' => 90]);

        $staff = $this->rosteredStylist();
        $a = $this->serviceFor($staff, 60);
        $b = $this->serviceFor($staff, 60);

        $this->actingAs($this->customer())
            ->post('/book/new', [
                'service_ids' => [$a->id, $b->id],
                'staff_id' => $staff->id,
                'starts_at' => $this->local(self::DATE.' 09:00')->toIso8601String(),
            ])
            ->assertSessionHasErrors('service_ids');

        $this->assertDatabaseCount('appointments', 0);
    }

    /* Inactive catalogue ------------------------------------------------------------- */

    public function test_an_inactive_service_cannot_be_booked(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $start = $this->firstSlot($staff, [$service]);

        $service->is_active = false;
        $service->save();

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $start))
            ->assertSessionHasErrors('service_ids.0');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_booking_with_no_services_is_refused(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($this->customer())
            ->post('/book/new', [
                'service_ids' => [],
                'staff_id' => $staff->id,
                'starts_at' => $this->local(self::DATE.' 09:00')->toIso8601String(),
            ])
            ->assertSessionHasErrors('service_ids');
    }

    /* Nothing partial is ever written --------------------------------------------------- */

    public function test_a_refused_booking_writes_nothing_at_all(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $start = $this->firstSlot($staff, [$service]);
        Appointment::factory()->forStaff($staff)->at($start, 60)->create();

        $before = Appointment::query()->count();

        $this->actingAs($this->customer())
            ->post('/book/new', $this->payload($staff, [$service], $start))
            ->assertSessionHasErrors();

        $this->assertSame($before, Appointment::query()->count());

        // The blocking appointment was created by a factory without items, so a
        // stray item would have to have come from the refused booking.
        $this->assertDatabaseCount('appointment_items', 0);
    }
}
