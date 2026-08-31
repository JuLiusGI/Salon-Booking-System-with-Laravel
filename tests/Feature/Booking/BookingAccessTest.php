<?php

namespace Tests\Feature\Booking;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

class BookingAccessTest extends TestCase
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

    /* The entry point ------------------------------------------------------- */

    public function test_a_guest_is_sent_to_register_and_returned_to_booking(): void
    {
        $this->get('/book')->assertRedirect(route('register'));

        $this->assertSame(route('booking.start'), session('url.intended'));
    }

    public function test_a_customer_is_taken_straight_into_the_booking_flow(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/book')
            ->assertRedirect(route('booking.create'));
    }

    public function test_salon_staff_are_sent_to_their_dashboard_instead(): void
    {
        foreach ([UserRole::Admin, UserRole::Receptionist, UserRole::Stylist] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/book')
                ->assertRedirect(route('dashboard'));
        }
    }

    /* The flow itself -------------------------------------------------------- */

    public function test_only_a_customer_can_open_the_booking_flow(): void
    {
        foreach (UserRole::cases() as $role) {
            $response = $this->actingAs(User::factory()->role($role)->create())->get('/book/new');

            $role === UserRole::Customer ? $response->assertOk() : $response->assertForbidden();
        }
    }

    public function test_only_a_customer_can_submit_a_booking(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        foreach ([UserRole::Admin, UserRole::Receptionist, UserRole::Stylist] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->post('/book/new', [
                    'service_ids' => [$service->id],
                    'staff_id' => $staff->id,
                    'starts_at' => $slot->startsAt->toIso8601String(),
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_guest_cannot_reach_the_booking_flow(): void
    {
        $this->get('/book/new')->assertRedirect(route('login'));
        $this->post('/book/new', [])->assertRedirect(route('login'));
    }

    public function test_a_customer_cannot_book_on_someone_elses_behalf(): void
    {
        $customer = User::factory()->create();
        $victim = User::factory()->create();

        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        $this->actingAs($customer)->post('/book/new', [
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $slot->startsAt->toIso8601String(),

            // Both ignored: the appointment belongs to whoever is signed in.
            'customer_id' => $victim->id,
            'status' => 'confirmed',
        ])->assertSessionHasNoErrors();

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame($customer->id, $appointment->customer_id);
        $this->assertNotSame($victim->id, $appointment->customer_id);
        $this->assertSame('pending', $appointment->status->value);
    }

    /* Viewing appointments ------------------------------------------------------ */

    public function test_a_customer_sees_only_their_own_appointments(): void
    {
        $customer = User::factory()->create();

        Appointment::factory()->count(2)->create(['customer_id' => $customer->id]);
        Appointment::factory()->count(3)->create();

        $this->actingAs($customer)
            ->get('/appointments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Appointments/Index')
                ->has('upcoming', 2)
            );
    }

    public function test_a_customer_cannot_open_another_customers_appointment(): void
    {
        $customer = User::factory()->create();
        $theirs = Appointment::factory()->create();

        $this->actingAs($customer)
            ->get("/appointments/{$theirs->reference}")
            ->assertForbidden();
    }

    public function test_a_customer_can_open_their_own_appointment(): void
    {
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($customer)
            ->get("/appointments/{$appointment->reference}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Appointments/Show')
                ->where('appointment.reference', $appointment->reference)
            );
    }

    public function test_the_desk_may_view_any_appointment(): void
    {
        $appointment = Appointment::factory()->create();

        foreach ([UserRole::Admin, UserRole::Receptionist] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get("/appointments/{$appointment->reference}")
                ->assertOk();
        }
    }

    public function test_a_stylist_may_view_their_own_work_but_not_another_stylists(): void
    {
        $mine = $this->rosteredStylist();
        $theirs = $this->rosteredStylist();

        $assigned = Appointment::factory()->forStaff($mine)->create();
        $unassigned = Appointment::factory()->forStaff($theirs)->create();

        $this->actingAs($mine->user)
            ->get("/appointments/{$assigned->reference}")
            ->assertOk();

        // Seeing another stylist's client list is not part of doing the work.
        $this->actingAs($mine->user)
            ->get("/appointments/{$unassigned->reference}")
            ->assertForbidden();
    }

    public function test_the_appointment_page_never_exposes_the_qr_token(): void
    {
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($customer)->get("/appointments/{$appointment->reference}");

        $response->assertDontSee($appointment->qr_token, escape: false);

        $response->assertInertia(fn (Assert $page) => $page->missing('appointment.qr_token'));
    }

    public function test_an_appointment_is_looked_up_by_reference_not_by_id(): void
    {
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id]);

        // A sequential id would let a customer walk the whole table.
        $this->actingAs($customer)
            ->get("/appointments/{$appointment->id}")
            ->assertNotFound();
    }

    public function test_a_guest_cannot_view_any_appointment(): void
    {
        $appointment = Appointment::factory()->create();

        $this->get('/appointments')->assertRedirect(route('login'));
        $this->get("/appointments/{$appointment->reference}")->assertRedirect(route('login'));
    }
}
