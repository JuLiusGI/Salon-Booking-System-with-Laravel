<?php

namespace Tests\Feature\Manage;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

/**
 * The appointment lifecycle from MASTER_SPEC section 9, driven through HTTP.
 */
class StatusTransitionTest extends TestCase
{
    use BuildsSalonSchedule, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo($this->local('2026-09-01 08:00'));
        $this->openSalon();
        $this->bookingRules();
    }

    private function desk(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function move(User $actor, Appointment $appointment, AppointmentStatus $to, ?string $reason = null)
    {
        return $this->actingAs($actor)->post(
            "/manage/appointments/{$appointment->reference}/status",
            array_filter(['status' => $to->value, 'reason' => $reason]),
        );
    }

    /* Valid moves ----------------------------------------------------------- */

    /**
     * @return array<string, array{0: AppointmentStatus, 1: AppointmentStatus}>
     */
    public static function validMoves(): array
    {
        return [
            'pending to confirmed' => [AppointmentStatus::Pending, AppointmentStatus::Confirmed],
            'pending to cancelled' => [AppointmentStatus::Pending, AppointmentStatus::Cancelled],
            'pending to no show' => [AppointmentStatus::Pending, AppointmentStatus::NoShow],
            'confirmed to checked in' => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn],
            'confirmed to in progress' => [AppointmentStatus::Confirmed, AppointmentStatus::InProgress],
            'confirmed to no show' => [AppointmentStatus::Confirmed, AppointmentStatus::NoShow],
            'checked in to in progress' => [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress],
            'in progress to completed' => [AppointmentStatus::InProgress, AppointmentStatus::Completed],
        ];
    }

    #[DataProvider('validMoves')]
    public function test_a_valid_move_is_applied(AppointmentStatus $from, AppointmentStatus $to): void
    {
        $appointment = Appointment::factory()->status($from)->create();

        $this->move($this->desk(), $appointment, $to)->assertSessionHasNoErrors();

        $this->assertSame($to, $appointment->fresh()->status);
    }

    /* Invalid moves ---------------------------------------------------------- */

    /**
     * @return array<string, array{0: AppointmentStatus, 1: AppointmentStatus}>
     */
    public static function invalidMoves(): array
    {
        return [
            'pending straight to completed' => [AppointmentStatus::Pending, AppointmentStatus::Completed],
            'pending straight to checked in' => [AppointmentStatus::Pending, AppointmentStatus::CheckedIn],
            'confirmed straight to completed' => [AppointmentStatus::Confirmed, AppointmentStatus::Completed],
            'checked in straight to completed' => [AppointmentStatus::CheckedIn, AppointmentStatus::Completed],
            'in progress back to confirmed' => [AppointmentStatus::InProgress, AppointmentStatus::Confirmed],
            'in progress to no show' => [AppointmentStatus::InProgress, AppointmentStatus::NoShow],
            'completed reopened' => [AppointmentStatus::Completed, AppointmentStatus::InProgress],
            'cancelled revived' => [AppointmentStatus::Cancelled, AppointmentStatus::Confirmed],
            'no show revived' => [AppointmentStatus::NoShow, AppointmentStatus::Confirmed],
        ];
    }

    #[DataProvider('invalidMoves')]
    public function test_an_invalid_move_is_refused(AppointmentStatus $from, AppointmentStatus $to): void
    {
        $appointment = Appointment::factory()->status($from)->create();

        $this->move($this->desk(), $appointment, $to)->assertForbidden();

        $this->assertSame($from, $appointment->fresh()->status);
    }

    public function test_a_terminal_appointment_can_never_be_moved_again(): void
    {
        foreach ([AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow] as $terminal) {
            $appointment = Appointment::factory()->status($terminal)->create();

            foreach (AppointmentStatus::cases() as $target) {
                $this->move($this->desk(), $appointment, $target)->assertForbidden();
            }

            $this->assertSame($terminal, $appointment->fresh()->status);
        }
    }

    public function test_moving_to_the_status_it_is_already_in_is_refused(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Confirmed)->create();

        $this->move($this->desk(), $appointment, AppointmentStatus::Confirmed)->assertForbidden();
    }

    /* Timestamps -------------------------------------------------------------- */

    public function test_each_step_records_when_it_happened(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Confirmed)->create();
        $desk = $this->desk();

        $this->assertNull($appointment->checked_in_at);

        $this->move($desk, $appointment, AppointmentStatus::CheckedIn);
        $this->assertNotNull($appointment->fresh()->checked_in_at);

        $this->move($desk, $appointment->fresh(), AppointmentStatus::InProgress);
        $this->assertNotNull($appointment->fresh()->started_at);

        $this->move($desk, $appointment->fresh(), AppointmentStatus::Completed);
        $this->assertNotNull($appointment->fresh()->completed_at);
    }

    public function test_cancelling_records_who_did_it_and_why(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Confirmed)->create();
        $desk = $this->desk();

        $this->move($desk, $appointment, AppointmentStatus::Cancelled, 'Customer called to cancel');

        $appointment->refresh();

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertSame($desk->id, $appointment->cancelled_by_id);
        $this->assertSame('Customer called to cancel', $appointment->cancellation_reason);
        $this->assertNotNull($appointment->cancelled_at);
    }

    public function test_every_status_change_is_audited(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Pending)->create();
        $desk = $this->desk();

        $this->move($desk, $appointment, AppointmentStatus::Confirmed);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'appointment.status_changed',
            'user_id' => $desk->id,
            'auditable_id' => $appointment->id,
        ]);
    }

    /* Effect on availability ---------------------------------------------------- */

    public function test_cancelling_releases_the_slot_back_into_availability(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $appointment = Appointment::factory()
            ->forStaff($staff)
            ->at($this->local('2026-09-15 11:00'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $engine = app(AvailabilityService::class);
        $before = $this->labels($engine->slotsFor($staff, collect([$service]), $this->localDate('2026-09-15')));

        $this->assertNotContains('11:00', $before);

        $this->move($this->desk(), $appointment, AppointmentStatus::Cancelled);

        $after = $this->labels($engine->slotsFor($staff, collect([$service]), $this->localDate('2026-09-15')));

        $this->assertContains('11:00', $after);
    }

    public function test_a_completed_appointment_still_holds_its_slot(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        Appointment::factory()
            ->forStaff($staff)
            ->at($this->local('2026-09-15 11:00'), 60)
            ->status(AppointmentStatus::Completed)
            ->create();

        $labels = $this->labels(
            app(AvailabilityService::class)
                ->slotsFor($staff, collect([$service]), $this->localDate('2026-09-15'))
        );

        // History still occupies the diary; only cancellations free time.
        $this->assertNotContains('11:00', $labels);
    }

    /* Who may move what ---------------------------------------------------------- */

    public function test_a_stylist_can_drive_their_own_appointment_but_not_check_someone_in(): void
    {
        $staff = $this->rosteredStylist();

        $appointment = Appointment::factory()
            ->forStaff($staff)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        // Checking a customer in is a front-desk job.
        $this->move($staff->user, $appointment, AppointmentStatus::CheckedIn)->assertForbidden();

        $this->move($staff->user, $appointment, AppointmentStatus::InProgress)->assertSessionHasNoErrors();
        $this->assertSame(AppointmentStatus::InProgress, $appointment->fresh()->status);

        $this->move($staff->user, $appointment->fresh(), AppointmentStatus::Completed)->assertSessionHasNoErrors();
        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
    }

    public function test_a_stylist_cannot_touch_another_stylists_appointment(): void
    {
        $mine = $this->rosteredStylist();
        $theirs = $this->rosteredStylist();

        $appointment = Appointment::factory()
            ->forStaff($theirs)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $this->move($mine->user, $appointment, AppointmentStatus::InProgress)->assertForbidden();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_a_customer_cannot_move_their_own_appointment_along(): void
    {
        $customer = User::factory()->create();

        $appointment = Appointment::factory()
            ->status(AppointmentStatus::Confirmed)
            ->create(['customer_id' => $customer->id]);

        // Marking yourself as completed is not a customer action.
        $this->move($customer, $appointment, AppointmentStatus::InProgress)->assertForbidden();
        $this->move($customer, $appointment, AppointmentStatus::CheckedIn)->assertForbidden();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_an_admin_may_make_any_valid_move(): void
    {
        $admin = User::factory()->admin()->create();
        $appointment = Appointment::factory()->status(AppointmentStatus::Confirmed)->create();

        $this->move($admin, $appointment, AppointmentStatus::CheckedIn)->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::CheckedIn, $appointment->fresh()->status);
    }

    public function test_a_guest_cannot_move_anything(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Confirmed)->create();

        $this->post("/manage/appointments/{$appointment->reference}/status", [
            'status' => AppointmentStatus::CheckedIn->value,
        ])->assertRedirect(route('login'));
    }

    public function test_an_unknown_status_value_is_rejected(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Confirmed)->create();

        $this->actingAs($this->desk())
            ->post("/manage/appointments/{$appointment->reference}/status", ['status' => 'finished'])
            ->assertSessionHasErrors('status');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_the_full_happy_path_runs_end_to_end(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Pending)->create();
        $desk = $this->desk();

        foreach ([
            AppointmentStatus::Confirmed,
            AppointmentStatus::CheckedIn,
            AppointmentStatus::InProgress,
            AppointmentStatus::Completed,
        ] as $step) {
            $this->move($desk, $appointment->fresh(), $step)->assertSessionHasNoErrors();
            $this->assertSame($step, $appointment->fresh()->status);
        }

        $appointment->refresh();

        $this->assertNotNull($appointment->checked_in_at);
        $this->assertNotNull($appointment->started_at);
        $this->assertNotNull($appointment->completed_at);
        $this->assertTrue($appointment->status->isTerminal());
    }
}
