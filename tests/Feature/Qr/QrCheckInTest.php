<?php

namespace Tests\Feature\Qr;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Qr\AppointmentQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

/**
 * QR generation, resolution, and check-in (MASTER_SPEC section 20).
 */
class QrCheckInTest extends TestCase
{
    use BuildsSalonSchedule, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo($this->local('2026-09-15 08:00'));
        $this->openSalon();
        $this->bookingRules();
    }

    private function desk(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function todaysAppointment(?AppointmentStatus $status = null): Appointment
    {
        $staff = $this->rosteredStylist();

        return Appointment::factory()
            ->forStaff($staff)
            ->at($this->local('2026-09-15 11:00'), 60)
            ->status($status ?? AppointmentStatus::Confirmed)
            ->create();
    }

    /* What the code contains -------------------------------------------------- */

    public function test_the_code_encodes_only_an_opaque_token_url(): void
    {
        $appointment = $this->todaysAppointment();

        $url = app(AppointmentQrService::class)->urlFor($appointment);

        $this->assertStringContainsString($appointment->qr_token, $url);

        // Nothing about the customer or the appointment is encoded alongside it.
        $this->assertStringNotContainsString($appointment->reference, $url);
        $this->assertStringNotContainsString($appointment->customer->name, $url);
        $this->assertStringNotContainsString($appointment->customer->email, $url);

        // The path is the token and nothing else.
        $this->assertSame('/qr/'.$appointment->qr_token, parse_url($url, PHP_URL_PATH));
    }

    public function test_the_customer_can_fetch_their_own_code_as_an_image(): void
    {
        $appointment = $this->todaysAppointment();

        $response = $this->actingAs($appointment->customer)
            ->get("/appointments/{$appointment->reference}/qr");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_the_code_image_is_never_publicly_cacheable(): void
    {
        $appointment = $this->todaysAppointment();

        $header = $this->actingAs($appointment->customer)
            ->get("/appointments/{$appointment->reference}/qr")
            ->headers->get('Cache-Control');

        // A shared cache must never hand one customer's code to another.
        $this->assertStringContainsString('private', $header);
        $this->assertStringNotContainsString('public', $header);
    }

    public function test_a_customer_cannot_fetch_someone_elses_code(): void
    {
        $appointment = $this->todaysAppointment();

        $this->actingAs(User::factory()->create())
            ->get("/appointments/{$appointment->reference}/qr")
            ->assertForbidden();
    }

    public function test_a_guest_cannot_fetch_a_code(): void
    {
        $appointment = $this->todaysAppointment();

        $this->get("/appointments/{$appointment->reference}/qr")->assertRedirect(route('login'));
    }

    public function test_the_raw_token_still_never_appears_in_the_page_itself(): void
    {
        $appointment = $this->todaysAppointment();

        $response = $this->actingAs($appointment->customer)
            ->get("/appointments/{$appointment->reference}");

        // The image is fetched from its own endpoint, so the token is not left
        // lying in the Inertia payload.
        $response->assertDontSee($appointment->qr_token, escape: false);
        $response->assertInertia(fn (Assert $page) => $page->missing('appointment.qr_token'));
    }

    /* Resolving a scanned code -------------------------------------------------- */

    public function test_staff_scanning_a_valid_code_see_the_appointment(): void
    {
        $appointment = $this->todaysAppointment();

        $this->actingAs($this->desk())
            ->get("/qr/{$appointment->qr_token}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/QrResult')
                ->where('found.reference', $appointment->reference)
                ->where('problem', null)
            );
    }

    public function test_an_unknown_code_gives_nothing_away(): void
    {
        $this->actingAs($this->desk())
            ->get('/qr/'.str_repeat('z', 64))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('found', null)
                ->where('problem', 'That code was not recognised. Look the appointment up by reference instead.')
            );
    }

    public function test_a_code_for_an_appointment_this_stylist_may_not_see_is_indistinguishable_from_an_unknown_one(): void
    {
        $mine = $this->rosteredStylist();
        $theirs = $this->rosteredStylist();

        $appointment = Appointment::factory()->forStaff($theirs)
            ->at($this->local('2026-09-15 11:00'), 60)
            ->create();

        $this->actingAs($mine->user)
            ->get("/qr/{$appointment->qr_token}")
            ->assertOk()
            // Same wording as an unknown code, so scanning cannot be used to
            // probe for valid tokens.
            ->assertInertia(fn (Assert $page) => $page
                ->where('found', null)
                ->where('problem', 'That code was not recognised. Look the appointment up by reference instead.')
            );
    }

    public function test_a_guest_scanning_a_code_is_sent_to_log_in(): void
    {
        $appointment = $this->todaysAppointment();

        // The code is a shortcut, never a credential.
        $this->get("/qr/{$appointment->qr_token}")->assertRedirect(route('login'));
    }

    public function test_a_customer_cannot_use_the_scanning_route_even_with_their_own_code(): void
    {
        $appointment = $this->todaysAppointment();

        // Scanning is a staff action. The customer already has the appointment
        // in their own account; they have no business on the desk's screen.
        $this->actingAs($appointment->customer)
            ->get("/qr/{$appointment->qr_token}")
            ->assertForbidden();
    }

    public function test_scanning_is_rate_limited(): void
    {
        $desk = $this->desk();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->actingAs($desk)->get('/qr/'.str_repeat('a', 64));
        }

        // A 64 character random token is not guessable, but there is no reason
        // to let anyone sit there trying.
        $this->actingAs($desk)
            ->get('/qr/'.str_repeat('a', 64))
            ->assertStatus(429);
    }

    /* Expiry --------------------------------------------------------------------- */

    public function test_a_code_for_a_cancelled_appointment_explains_itself(): void
    {
        $appointment = $this->todaysAppointment(AppointmentStatus::Cancelled);

        $this->actingAs($this->desk())
            ->get("/qr/{$appointment->qr_token}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('found.reference', $appointment->reference)
                ->where('problem', 'This appointment was cancelled.')
            );
    }

    public function test_a_code_for_a_completed_appointment_cannot_be_reused(): void
    {
        $appointment = $this->todaysAppointment(AppointmentStatus::Completed);

        $this->actingAs($this->desk())
            ->get("/qr/{$appointment->qr_token}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('problem', 'This appointment has already been completed.')
                ->where('found.can_check_in', false)
            );
    }

    public function test_a_code_for_a_long_past_appointment_has_expired(): void
    {
        $staff = $this->rosteredStylist();

        $appointment = Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-01 11:00'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $this->actingAs($this->desk())
            ->get("/qr/{$appointment->qr_token}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('problem', 'This code is for an appointment that has already passed.')
            );
    }

    public function test_a_code_is_not_active_long_before_the_appointment(): void
    {
        $staff = $this->rosteredStylist();

        $appointment = Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-10-20 11:00'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $this->actingAs($this->desk())
            ->get("/qr/{$appointment->qr_token}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('problem', 'This code is not active yet. It works from a week before the appointment.')
            );
    }

    /* Check-in ------------------------------------------------------------------- */

    public function test_the_desk_can_check_someone_in_from_a_scanned_code(): void
    {
        $appointment = $this->todaysAppointment();

        $this->actingAs($this->desk())
            ->post("/manage/check-in/{$appointment->reference}")
            ->assertSessionHasNoErrors();

        $appointment->refresh();

        $this->assertSame(AppointmentStatus::CheckedIn, $appointment->status);
        $this->assertNotNull($appointment->checked_in_at);
    }

    public function test_checking_in_is_the_same_transition_however_it_was_reached(): void
    {
        $appointment = $this->todaysAppointment();

        $this->actingAs($this->desk())->post("/manage/check-in/{$appointment->reference}");

        // Goes through AppointmentStatusService, so it is audited like any other
        // status change rather than being a special back door.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'appointment.status_changed',
            'auditable_id' => $appointment->id,
        ]);
    }

    public function test_a_customer_cannot_check_themselves_in(): void
    {
        $appointment = $this->todaysAppointment();

        $this->actingAs($appointment->customer)
            ->post("/manage/check-in/{$appointment->reference}")
            ->assertForbidden();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_a_stylist_cannot_check_someone_in(): void
    {
        $staff = $this->rosteredStylist();

        $appointment = Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-15 11:00'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        // Even their own appointment: the desk owns arrival.
        $this->actingAs($staff->user)
            ->post("/manage/check-in/{$appointment->reference}")
            ->assertForbidden();
    }

    public function test_an_already_checked_in_appointment_cannot_be_checked_in_again(): void
    {
        $appointment = $this->todaysAppointment(AppointmentStatus::CheckedIn);

        $this->actingAs($this->desk())
            ->post("/manage/check-in/{$appointment->reference}")
            ->assertForbidden();
    }

    /* Working without a code -------------------------------------------------------- */

    public function test_the_desk_can_find_an_appointment_by_reference_without_any_code(): void
    {
        $appointment = $this->todaysAppointment();

        $this->actingAs($this->desk())
            ->get('/manage/check-in?reference='.$appointment->reference)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/CheckIn')
                ->where('found.reference', $appointment->reference)
            );
    }

    public function test_an_unknown_reference_reports_nothing_found(): void
    {
        $this->actingAs($this->desk())
            ->get('/manage/check-in?reference=SB-DOESNOTEXIST')
            ->assertInertia(fn (Assert $page) => $page->where('found', null));
    }

    public function test_the_check_in_desk_lists_todays_arrivals(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-15 10:00'), 60)
            ->status(AppointmentStatus::Confirmed)->create();
        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-15 14:00'), 60)
            ->status(AppointmentStatus::Pending)->create();

        // Not today, and already finished, so neither belongs on the desk.
        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-16 10:00'), 60)->create();
        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-15 09:00'), 60)
            ->status(AppointmentStatus::Completed)->create();

        $this->actingAs($this->desk())
            ->get('/manage/check-in')
            ->assertInertia(fn (Assert $page) => $page->has('arrivals', 2));
    }

    public function test_a_customer_cannot_open_the_check_in_desk(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Customer)->create())
            ->get('/manage/check-in')
            ->assertForbidden();
    }

    public function test_allergies_are_surfaced_on_the_check_in_card(): void
    {
        $appointment = $this->todaysAppointment();
        $appointment->customer->customerProfile()->create(['allergies' => 'Reacts to ammonia']);

        $this->actingAs($this->desk())
            ->get('/manage/check-in')
            ->assertInertia(fn (Assert $page) => $page->where('arrivals.0.allergies', 'Reacts to ammonia'));
    }
}
