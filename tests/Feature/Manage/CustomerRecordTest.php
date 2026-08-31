<?php

namespace Tests\Feature\Manage;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

/**
 * Customer records hold health information, so access is narrower than for the
 * rest of the diary and every edit is traceable.
 */
class CustomerRecordTest extends TestCase
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

    /* Access ---------------------------------------------------------------- */

    public function test_only_the_desk_can_browse_the_customer_directory(): void
    {
        foreach (UserRole::cases() as $role) {
            $response = $this->actingAs(User::factory()->role($role)->create())
                ->get('/manage/customers');

            in_array($role, [UserRole::Admin, UserRole::Receptionist], true)
                ? $response->assertOk()
                : $response->assertForbidden();
        }
    }

    public function test_a_guest_cannot_browse_customers(): void
    {
        $this->get('/manage/customers')->assertRedirect(route('login'));
    }

    public function test_a_stylist_can_open_a_customer_they_are_treating(): void
    {
        $staff = $this->rosteredStylist();
        $customer = User::factory()->create();

        Appointment::factory()->forStaff($staff)->create(['customer_id' => $customer->id]);

        $this->actingAs($staff->user)
            ->get("/manage/customers/{$customer->id}")
            ->assertOk();
    }

    public function test_a_stylist_cannot_open_a_customer_they_have_never_seen(): void
    {
        $staff = $this->rosteredStylist();
        $other = $this->rosteredStylist();
        $customer = User::factory()->create();

        Appointment::factory()->forStaff($other)->create(['customer_id' => $customer->id]);

        $this->actingAs($staff->user)
            ->get("/manage/customers/{$customer->id}")
            ->assertForbidden();
    }

    public function test_a_customer_cannot_open_another_customers_record(): void
    {
        $customer = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/manage/customers/{$customer->id}")
            ->assertForbidden();
    }

    public function test_a_staff_account_is_not_reachable_as_a_customer_record(): void
    {
        $staff = $this->rosteredStylist();

        // The route is for customers; a staff id must not resolve through it.
        $this->actingAs($this->desk())
            ->get("/manage/customers/{$staff->user_id}")
            ->assertNotFound();
    }

    /* What a stylist sees ------------------------------------------------------ */

    public function test_a_stylist_sees_the_clinical_notes_but_not_the_desk_notes(): void
    {
        $staff = $this->rosteredStylist();
        $customer = User::factory()->create();

        $customer->customerProfile()->create([
            'allergies' => 'Reacts to ammonia',
            'preferences' => 'Prefers a quiet chair',
            'notes' => 'Disputed a charge in March',
        ]);

        Appointment::factory()->forStaff($staff)->create(['customer_id' => $customer->id]);

        $this->actingAs($staff->user)
            ->get("/manage/customers/{$customer->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.allergies', 'Reacts to ammonia')
                ->where('customer.preferences', 'Prefers a quiet chair')
                // Front-desk commentary is not a stylist's business.
                ->where('customer.notes', null)
                ->where('can.manage', false)
            );
    }

    public function test_the_desk_sees_everything_including_its_own_notes(): void
    {
        $customer = User::factory()->create();
        $customer->customerProfile()->create(['notes' => 'Disputed a charge in March']);

        $this->actingAs($this->desk())
            ->get("/manage/customers/{$customer->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.notes', 'Disputed a charge in March')
                ->where('can.manage', true)
            );
    }

    public function test_a_stylist_only_sees_the_visits_they_worked_on(): void
    {
        $mine = $this->rosteredStylist();
        $theirs = $this->rosteredStylist();
        $customer = User::factory()->create();

        Appointment::factory()->forStaff($mine)->count(2)->create(['customer_id' => $customer->id]);
        Appointment::factory()->forStaff($theirs)->count(3)->create(['customer_id' => $customer->id]);

        $this->actingAs($mine->user)
            ->get("/manage/customers/{$customer->id}")
            ->assertInertia(fn (Assert $page) => $page->has('history', 2));
    }

    public function test_the_desk_sees_the_whole_history(): void
    {
        $first = $this->rosteredStylist();
        $second = $this->rosteredStylist();
        $customer = User::factory()->create();

        Appointment::factory()->forStaff($first)->count(2)->create(['customer_id' => $customer->id]);
        Appointment::factory()->forStaff($second)->count(3)->create(['customer_id' => $customer->id]);

        $this->actingAs($this->desk())
            ->get("/manage/customers/{$customer->id}")
            ->assertInertia(fn (Assert $page) => $page->has('history', 5));
    }

    /* Editing -------------------------------------------------------------------- */

    public function test_the_desk_can_update_a_customer_record(): void
    {
        $customer = User::factory()->create();
        $customer->customerProfile()->create([]);

        $this->actingAs($this->desk())
            ->patch("/manage/customers/{$customer->id}", [
                'name' => 'Renamed Customer',
                'email' => $customer->email,
                'phone' => '09171234567',
                'allergies' => 'Sensitive to bleach',
                'service_notes' => 'Formula 6.1 with 20 vol',
            ])
            ->assertSessionHasNoErrors();

        $customer->refresh();

        $this->assertSame('Renamed Customer', $customer->name);
        $this->assertSame('Sensitive to bleach', $customer->customerProfile->allergies);
        $this->assertSame('Formula 6.1 with 20 vol', $customer->customerProfile->service_notes);
    }

    public function test_a_stylist_cannot_edit_a_customer_record(): void
    {
        $staff = $this->rosteredStylist();
        $customer = User::factory()->create();

        Appointment::factory()->forStaff($staff)->create(['customer_id' => $customer->id]);

        $this->actingAs($staff->user)
            ->patch("/manage/customers/{$customer->id}", [
                'name' => 'Changed',
                'email' => $customer->email,
            ])
            ->assertForbidden();

        $this->assertNotSame('Changed', $customer->fresh()->name);
    }

    public function test_editing_a_customer_record_is_audited_without_logging_the_values(): void
    {
        $desk = $this->desk();
        $customer = User::factory()->create();
        $customer->customerProfile()->create([]);

        $this->actingAs($desk)->patch("/manage/customers/{$customer->id}", [
            'name' => $customer->name,
            'email' => $customer->email,
            'allergies' => 'Severe latex allergy',
        ]);

        $log = AuditLog::query()->where('action', 'customer.updated')->firstOrFail();

        $this->assertSame($desk->id, $log->user_id);

        // The trail records that a change happened, never the health data itself.
        $this->assertStringNotContainsString('latex', json_encode($log->metadata));
    }

    public function test_an_email_already_in_use_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        $customer = User::factory()->create();

        $this->actingAs($this->desk())
            ->patch("/manage/customers/{$customer->id}", [
                'name' => $customer->name,
                'email' => 'taken@example.test',
            ])
            ->assertSessionHasErrors('email');
    }

    /* Creating --------------------------------------------------------------------- */

    public function test_the_desk_can_add_a_walk_in_customer(): void
    {
        $this->actingAs($this->desk())
            ->post('/manage/customers/new', [
                'name' => 'Walk In Wanda',
                'email' => 'wanda@example.test',
                'phone' => '09170000000',
            ])
            ->assertSessionHasNoErrors();

        $customer = User::where('email', 'wanda@example.test')->firstOrFail();

        $this->assertSame(UserRole::Customer, $customer->role);
        $this->assertNotNull($customer->customerProfile);
    }

    public function test_a_desk_created_customer_gets_a_password_nobody_knows(): void
    {
        $this->actingAs($this->desk())->post('/manage/customers/new', [
            'name' => 'Walk In Wanda',
            'email' => 'wanda@example.test',
        ]);

        // Leave the desk's session behind before testing the new account.
        $this->post('/logout');

        // The desk never handles a password; the customer claims the account
        // through the reset flow.
        $this->post('/login', ['email' => 'wanda@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_stylist_cannot_add_a_customer(): void
    {
        $this->actingAs($this->rosteredStylist()->user)
            ->post('/manage/customers/new', ['name' => 'Nope', 'email' => 'nope@example.test'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nope@example.test']);
    }

    /* Stats ---------------------------------------------------------------------- */

    public function test_the_summary_counts_visits_cancellations_and_no_shows(): void
    {
        $staff = $this->rosteredStylist();
        $customer = User::factory()->create();

        Appointment::factory()->forStaff($staff)->count(3)
            ->create(['customer_id' => $customer->id, 'status' => AppointmentStatus::Completed]);
        Appointment::factory()->forStaff($staff)->count(2)
            ->create(['customer_id' => $customer->id, 'status' => AppointmentStatus::Cancelled]);
        Appointment::factory()->forStaff($staff)
            ->create(['customer_id' => $customer->id, 'status' => AppointmentStatus::NoShow]);

        $this->actingAs($this->desk())
            ->get("/manage/customers/{$customer->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.visits', 3)
                ->where('stats.cancelled', 2)
                ->where('stats.no_shows', 1)
            );
    }

    public function test_the_directory_flags_an_allergy_without_showing_it(): void
    {
        $customer = User::factory()->create();
        $customer->customerProfile()->create(['allergies' => 'Severe latex allergy']);

        $response = $this->actingAs($this->desk())->get('/manage/customers');

        $response->assertInertia(fn (Assert $page) => $page->where('customers.data.0.has_allergies', true));

        // The list says there is something to know, not what it is.
        $response->assertDontSee('Severe latex allergy', escape: false);
    }

    public function test_the_directory_can_be_searched(): void
    {
        User::factory()->create(['name' => 'Findable Person', 'email' => 'findable@example.test']);
        User::factory()->count(3)->create();

        $this->actingAs($this->desk())
            ->get('/manage/customers?search=Findable')
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Findable Person')
            );
    }

    public function test_staff_accounts_never_appear_in_the_customer_directory(): void
    {
        User::factory()->count(2)->create();
        $this->rosteredStylist();
        User::factory()->admin()->create();

        $this->actingAs($this->desk())
            ->get('/manage/customers')
            ->assertInertia(fn (Assert $page) => $page->has('customers.data', 2));
    }
}
