<?php

namespace Tests\Feature\Manage;

use App\Enums\AppointmentSource;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

/**
 * Booking on a customer's behalf, at the desk or over the phone.
 */
class StaffBookingTest extends TestCase
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

    public function test_only_the_desk_can_open_the_staff_booking_screen(): void
    {
        foreach (UserRole::cases() as $role) {
            $response = $this->actingAs(User::factory()->role($role)->create())
                ->get('/manage/appointments/new');

            in_array($role, [UserRole::Admin, UserRole::Receptionist], true)
                ? $response->assertOk()
                : $response->assertForbidden();
        }
    }

    public function test_the_desk_can_book_for_a_customer(): void
    {
        $desk = User::factory()->receptionist()->create();
        $customer = User::factory()->create();
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60, 750.00);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        $this->actingAs($desk)->post('/manage/appointments/new', [
            'customer_id' => $customer->id,
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $slot->startsAt->toIso8601String(),
            'source' => AppointmentSource::Phone->value,
            'notes' => 'Called in this morning.',
        ])->assertSessionHasNoErrors();

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame($customer->id, $appointment->customer_id);
        $this->assertSame(AppointmentSource::Phone, $appointment->source);

        // Recorded so the salon can tell who took the booking.
        $this->assertSame($desk->id, $appointment->booked_by_id);
        $this->assertSame('Called in this morning.', $appointment->notes);
    }

    public function test_a_stylist_cannot_book_for_a_customer(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $customer = User::factory()->create();

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        $this->actingAs($staff->user)->post('/manage/appointments/new', [
            'customer_id' => $customer->id,
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $slot->startsAt->toIso8601String(),
            'source' => AppointmentSource::WalkIn->value,
        ])->assertForbidden();

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_staff_booking_cannot_be_made_for_a_non_customer_account(): void
    {
        $desk = User::factory()->receptionist()->create();
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        // Booking a stylist in as their own client is a mistake, not a feature.
        $this->actingAs($desk)->post('/manage/appointments/new', [
            'customer_id' => $staff->user_id,
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $slot->startsAt->toIso8601String(),
            'source' => AppointmentSource::WalkIn->value,
        ])->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_staff_booking_goes_through_the_same_conflict_protection(): void
    {
        $desk = User::factory()->receptionist()->create();
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        Appointment::factory()->forStaff($staff)->at($slot->startsAt, 60)->create();

        $this->actingAs($desk)->post('/manage/appointments/new', [
            'customer_id' => User::factory()->create()->id,
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $slot->startsAt->toIso8601String(),
            'source' => AppointmentSource::WalkIn->value,
        ])->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_the_customer_search_only_returns_customers_and_only_when_asked(): void
    {
        $desk = User::factory()->receptionist()->create();

        User::factory()->create(['name' => 'Nadia Ocampo']);
        User::factory()->stylist()->create(['name' => 'Nadia The Stylist']);

        // No search means no list, rather than dumping every customer into the
        // page on load.
        $this->actingAs($desk)
            ->get('/manage/appointments/new')
            ->assertInertia(fn (Assert $page) => $page->has('customers', 0));

        $this->actingAs($desk)
            ->get('/manage/appointments/new?customer_search=Nadia')
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers', 1)
                ->where('customers.0.name', 'Nadia Ocampo')
            );
    }

    public function test_the_staff_booking_screen_only_offers_stylists_who_can_do_every_service(): void
    {
        $desk = User::factory()->receptionist()->create();

        $first = $this->rosteredStylist();
        $second = $this->rosteredStylist();

        $shared = $this->serviceFor($first, 30);
        $shared->staff()->attach($second);

        $exclusive = $this->serviceFor($first, 30);

        $this->actingAs($desk)
            ->get('/manage/appointments/new?service_ids[]='.$shared->id)
            ->assertInertia(fn (Assert $page) => $page->has('stylists', 2));

        $this->actingAs($desk)
            ->get('/manage/appointments/new?service_ids[]='.$shared->id.'&service_ids[]='.$exclusive->id)
            ->assertInertia(fn (Assert $page) => $page->has('stylists', 1));
    }
}
