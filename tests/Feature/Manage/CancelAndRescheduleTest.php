<?php

namespace Tests\Feature\Manage;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

class CancelAndRescheduleTest extends TestCase
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

    /**
     * A real appointment with items, so rescheduling can resolve its services.
     */
    private function booked(Staff $staff, Service $service, string $at = self::DATE.' 11:00'): Appointment
    {
        $appointment = Appointment::factory()
            ->forStaff($staff)
            ->at($this->local($at), $service->duration_minutes)
            ->status(AppointmentStatus::Confirmed)
            ->create(['source' => AppointmentSource::Online]);

        AppointmentItem::factory()->forService($service)->create([
            'appointment_id' => $appointment->id,
        ]);

        return $appointment->fresh(['items']);
    }

    /* Cancellation ---------------------------------------------------------- */

    public function test_a_customer_can_cancel_before_the_deadline(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);
        $customer = $appointment->customer;

        $this->actingAs($customer)
            ->post("/appointments/{$appointment->reference}/cancel", ['reason' => 'Something came up'])
            ->assertSessionHasNoErrors();

        $appointment->refresh();

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertSame($customer->id, $appointment->cancelled_by_id);
        $this->assertSame('Something came up', $appointment->cancellation_reason);
    }

    public function test_a_customer_cannot_cancel_after_the_deadline(): void
    {
        $this->bookingRules(['cancellation_deadline_hours' => 24]);

        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);

        // Inside the 24 hour notice period.
        $this->travelTo($this->local(self::DATE.' 08:00'));

        $this->actingAs($appointment->customer)
            ->post("/appointments/{$appointment->reference}/cancel")
            ->assertForbidden();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_the_desk_can_cancel_even_after_the_deadline(): void
    {
        $this->bookingRules(['cancellation_deadline_hours' => 24]);

        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);

        $this->travelTo($this->local(self::DATE.' 08:00'));

        // A phone call is a legitimate way to cancel late.
        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/appointments/{$appointment->reference}/cancel", ['reason' => 'Called in'])
            ->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
    }

    public function test_a_customer_cannot_cancel_someone_elses_appointment(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);

        $this->actingAs(User::factory()->create())
            ->post("/appointments/{$appointment->reference}/cancel")
            ->assertForbidden();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_an_already_cancelled_appointment_cannot_be_cancelled_again(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);
        $appointment->forceFill(['status' => AppointmentStatus::Cancelled])->save();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/appointments/{$appointment->reference}/cancel")
            ->assertForbidden();
    }

    /* Rescheduling ------------------------------------------------------------ */

    private function freeSlotAfterCancelling(Staff $staff, Service $service, string $date = self::DATE): CarbonImmutable
    {
        return app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate($date))
            ->first()
            ->startsAt;
    }

    public function test_rescheduling_creates_a_replacement_and_cancels_the_original(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);

        $target = $this->freeSlotAfterCancelling($staff, $service);

        $this->actingAs($appointment->customer)
            ->post("/appointments/{$appointment->reference}/reschedule", [
                'starts_at' => $target->toIso8601String(),
            ])
            ->assertSessionHasNoErrors();

        $appointment->refresh();

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertSame('Rescheduled', $appointment->cancellation_reason);

        $replacement = Appointment::query()
            ->where('rescheduled_from_id', $appointment->id)
            ->firstOrFail();

        $this->assertTrue($replacement->starts_at->equalTo($target));
        $this->assertSame($appointment->customer_id, $replacement->customer_id);
        $this->assertCount(1, $replacement->items);

        // The replacement keeps the service as originally booked.
        $this->assertSame($service->name, $replacement->items->first()->service_name);
    }

    public function test_an_appointment_can_be_moved_into_its_own_old_slot_with_another_stylist(): void
    {
        $first = $this->rosteredStylist();
        $second = $this->rosteredStylist();

        $service = $this->serviceFor($first, 60);
        $service->staff()->attach($second);

        $appointment = $this->booked($first, $service);
        $sameTime = $appointment->starts_at;

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/appointments/{$appointment->reference}/reschedule", [
                'starts_at' => $sameTime->toIso8601String(),
                'staff_id' => $second->id,
            ])
            ->assertSessionHasNoErrors();

        $replacement = Appointment::query()->where('rescheduled_from_id', $appointment->id)->firstOrFail();

        $this->assertSame($second->id, $replacement->staff_id);
        $this->assertTrue($replacement->starts_at->equalTo($sameTime));
    }

    public function test_rescheduling_into_a_taken_slot_is_refused_and_changes_nothing(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $appointment = $this->booked($staff, $service, self::DATE.' 11:00');
        $blocker = $this->booked($staff, $service, self::DATE.' 14:00');

        $this->actingAs($appointment->customer)
            ->post("/appointments/{$appointment->reference}/reschedule", [
                'starts_at' => $blocker->starts_at->toIso8601String(),
            ])
            ->assertSessionHasErrors('starts_at');

        // The original must survive intact: a failed move cannot leave the
        // customer with no appointment at all.
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_a_customer_cannot_reschedule_after_the_deadline(): void
    {
        $this->bookingRules(['reschedule_deadline_hours' => 24]);

        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);

        $this->travelTo($this->local(self::DATE.' 08:00'));

        $this->actingAs($appointment->customer)
            ->get("/appointments/{$appointment->reference}/reschedule")
            ->assertForbidden();

        $this->actingAs($appointment->customer)
            ->post("/appointments/{$appointment->reference}/reschedule", [
                'starts_at' => $this->local(self::DATE.' 15:00')->toIso8601String(),
            ])
            ->assertForbidden();
    }

    public function test_a_customer_cannot_reschedule_someone_elses_appointment(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);

        $this->actingAs(User::factory()->create())
            ->post("/appointments/{$appointment->reference}/reschedule", [
                'starts_at' => $this->local(self::DATE.' 15:00')->toIso8601String(),
            ])
            ->assertForbidden();
    }

    public function test_a_cancelled_appointment_cannot_be_rescheduled(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);
        $appointment->forceFill(['status' => AppointmentStatus::Cancelled])->save();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/appointments/{$appointment->reference}/reschedule", [
                'starts_at' => $this->local(self::DATE.' 15:00')->toIso8601String(),
            ])
            ->assertForbidden();
    }

    public function test_an_appointment_whose_service_left_the_menu_cannot_be_moved_automatically(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);

        // Hard delete, so the item's service_id is nulled and the original
        // service can no longer be resolved.
        $service->forceDelete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/appointments/{$appointment->reference}/reschedule", [
                'starts_at' => $this->local(self::DATE.' 15:00')->toIso8601String(),
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_rescheduling_is_audited(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);
        $appointment = $this->booked($staff, $service);
        $desk = User::factory()->receptionist()->create();

        $this->actingAs($desk)->post("/appointments/{$appointment->reference}/reschedule", [
            'starts_at' => $this->freeSlotAfterCancelling($staff, $service)->toIso8601String(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'appointment.rescheduled',
            'user_id' => $desk->id,
        ]);
    }

    /* Notes ---------------------------------------------------------------------- */

    public function test_the_desk_can_add_internal_notes(): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch("/manage/appointments/{$appointment->reference}", [
                'internal_notes' => 'Prefers the quiet chair by the window.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Prefers the quiet chair by the window.', $appointment->fresh()->internal_notes);
    }

    public function test_a_customer_cannot_edit_internal_notes(): void
    {
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($customer)
            ->patch("/manage/appointments/{$appointment->reference}", ['internal_notes' => 'Sneaky'])
            ->assertForbidden();

        $this->assertNull($appointment->fresh()->internal_notes);
    }

    public function test_internal_notes_are_never_sent_to_the_customer(): void
    {
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'internal_notes' => 'Difficult about the fringe.',
        ]);

        $this->actingAs($customer)
            ->get("/appointments/{$appointment->reference}")
            ->assertDontSee('Difficult about the fringe.', escape: false);
    }
}
