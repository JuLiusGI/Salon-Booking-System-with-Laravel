<?php

namespace Tests\Feature\Notifications;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentReminder;
use App\Services\Availability\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

class AppointmentNotificationTest extends TestCase
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

    /* Booking ---------------------------------------------------------------- */

    public function test_booking_notifies_the_customer(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        $this->actingAs($customer)->post('/book/new', [
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $slot->startsAt->toIso8601String(),
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($customer, AppointmentBooked::class);
    }

    public function test_a_refused_booking_notifies_nobody(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $slot = app(AvailabilityService::class)
            ->slotsFor($staff, collect([$service]), $this->localDate(self::DATE))
            ->first();

        Appointment::factory()->forStaff($staff)->at($slot->startsAt, 60)->create();

        $this->actingAs($customer)->post('/book/new', [
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $slot->startsAt->toIso8601String(),
        ])->assertSessionHasErrors();

        Notification::assertNothingSent();
    }

    /* Status changes ----------------------------------------------------------- */

    public function test_confirming_notifies_the_customer(): void
    {
        Notification::fake();

        $appointment = Appointment::factory()->status(AppointmentStatus::Pending)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/manage/appointments/{$appointment->reference}/status", [
                'status' => AppointmentStatus::Confirmed->value,
            ]);

        Notification::assertSentTo($appointment->customer, AppointmentConfirmed::class);
    }

    public function test_cancelling_notifies_the_customer(): void
    {
        Notification::fake();

        $appointment = Appointment::factory()->status(AppointmentStatus::Confirmed)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/appointments/{$appointment->reference}/cancel", ['reason' => 'Closed for the day']);

        Notification::assertSentTo($appointment->customer, AppointmentCancelled::class);
    }

    public function test_routine_bookkeeping_does_not_pester_the_customer(): void
    {
        Notification::fake();

        $appointment = Appointment::factory()->status(AppointmentStatus::Confirmed)->create();
        $desk = User::factory()->receptionist()->create();

        foreach ([AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::Completed] as $step) {
            $this->actingAs($desk)->post(
                "/manage/appointments/{$appointment->fresh()->reference}/status",
                ['status' => $step->value],
            );
        }

        // The customer was standing there for all of it.
        Notification::assertNothingSent();
    }

    public function test_a_deactivated_customer_is_not_written_to(): void
    {
        Notification::fake();

        $customer = User::factory()->inactive()->create();
        $appointment = Appointment::factory()
            ->status(AppointmentStatus::Pending)
            ->create(['customer_id' => $customer->id]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/manage/appointments/{$appointment->reference}/status", [
                'status' => AppointmentStatus::Confirmed->value,
            ]);

        Notification::assertNothingSent();
    }

    /* What is actually stored ---------------------------------------------------- */

    public function test_a_notification_is_written_to_the_database(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Pending)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/manage/appointments/{$appointment->reference}/status", [
                'status' => AppointmentStatus::Confirmed->value,
            ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AppointmentConfirmed::class,
            'notifiable_id' => $appointment->customer_id,
        ]);
    }

    public function test_the_stored_payload_carries_no_personal_or_internal_data(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Pending)->create([
            'internal_notes' => 'Difficult about the fringe',
        ]);

        $appointment->customer->customerProfile()->create([
            'allergies' => 'Severe latex allergy',
            'notes' => 'Disputed a charge',
        ]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/manage/appointments/{$appointment->reference}/status", [
                'status' => AppointmentStatus::Confirmed->value,
            ]);

        $payload = \DB::table('notifications')->value('data');

        // A stored notification is rendered in a browser and mirrored into mail,
        // so it must never carry anything beyond what the message needs.
        $this->assertStringNotContainsString('latex', $payload);
        $this->assertStringNotContainsString('fringe', $payload);
        $this->assertStringNotContainsString('Disputed', $payload);
        $this->assertStringNotContainsString($appointment->qr_token, $payload);
        $this->assertStringNotContainsString($appointment->customer->email, $payload);

        // But it does carry what a customer needs to recognise it.
        $this->assertStringContainsString($appointment->reference, $payload);
    }

    public function test_the_payload_shape_is_pinned(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Pending)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post("/manage/appointments/{$appointment->reference}/status", [
                'status' => AppointmentStatus::Confirmed->value,
            ]);

        $data = json_decode(\DB::table('notifications')->value('data'), true);

        // Adding a key must be a deliberate decision, not a drift.
        $this->assertSame([
            'date', 'headline', 'reference', 'services', 'staff_name', 'time', 'type', 'url',
        ], collect(array_keys($data))->sort()->values()->all());
    }

    /* The customer's own list ------------------------------------------------------ */

    public function test_a_customer_sees_only_their_own_notifications(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $mine->notify(new AppointmentBooked(Appointment::factory()->create(['customer_id' => $mine->id])));
        $theirs->notify(new AppointmentBooked(Appointment::factory()->create(['customer_id' => $theirs->id])));

        $this->actingAs($mine)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('notifications', 1));
    }

    public function test_a_guest_cannot_read_notifications(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
    }

    public function test_notifications_can_be_marked_read(): void
    {
        $customer = User::factory()->create();
        $customer->notify(new AppointmentBooked(Appointment::factory()->create(['customer_id' => $customer->id])));

        $this->assertSame(1, $customer->unreadNotifications()->count());

        $this->actingAs($customer)->post('/notifications/read-all')->assertSessionHasNoErrors();

        $this->assertSame(0, $customer->fresh()->unreadNotifications()->count());
    }

    public function test_a_customer_cannot_mark_someone_elses_notification_read(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new AppointmentBooked(Appointment::factory()->create(['customer_id' => $owner->id])));

        $id = $owner->notifications()->first()->id;

        $this->actingAs(User::factory()->create())
            ->post("/notifications/{$id}/read")
            ->assertNotFound();

        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
    }

    /* Reminders --------------------------------------------------------------------- */

    public function test_the_reminder_command_notifies_appointments_due_tomorrow(): void
    {
        Notification::fake();

        $staff = $this->rosteredStylist();

        $due = Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-02 07:30'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        // Too far out to be tomorrow's business.
        Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-20 09:00'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $this->artisan('appointments:remind')->assertSuccessful();

        Notification::assertSentTo($due->customer, AppointmentReminder::class);
        Notification::assertSentTimes(AppointmentReminder::class, 1);
    }

    public function test_a_cancelled_appointment_is_never_reminded_about(): void
    {
        Notification::fake();

        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-02 07:30'), 60)
            ->cancelled()
            ->create();

        $this->artisan('appointments:remind')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_running_the_reminder_twice_does_not_send_twice(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-02 07:30'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $this->artisan('appointments:remind')->assertSuccessful();
        $this->artisan('appointments:remind')->assertSuccessful();

        // Catching up after a missed run must not mean a second reminder.
        $this->assertSame(1, \DB::table('notifications')
            ->where('type', AppointmentReminder::class)
            ->count());
    }

    public function test_a_dry_run_sends_nothing(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-02 07:30'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $this->artisan('appointments:remind --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }
}
