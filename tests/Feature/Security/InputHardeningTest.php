<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filter parameters arrive from the query string, so they are attacker-controlled
 * in the same way a form field is. A malformed one must narrow or ignore the
 * filter, never crash the page: a 500 is both a broken screen and a signal about
 * the internals to whoever is probing.
 */
class InputHardeningTest extends TestCase
{
    use RefreshDatabase;

    /* The admin user directory ---------------------------------------------- */

    public function test_an_unknown_role_filter_does_not_crash_the_user_directory(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/users?role=wizard')
            ->assertOk();
    }

    public function test_a_known_role_filter_still_narrows_the_directory(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->stylist()->create();
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get('/admin/users?role=stylist')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('users.data', 1));
    }

    /* The appointment list --------------------------------------------------- */

    public function test_a_malformed_date_filter_does_not_crash_the_appointment_list(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/manage/appointments?from=not-a-date&to=also-not-a-date')
            ->assertOk();
    }

    public function test_a_well_formed_date_filter_is_still_applied(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/manage/appointments?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.from', '2026-01-01'));
    }

    public function test_an_unknown_status_filter_returns_an_empty_list_rather_than_an_error(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/manage/appointments?status=teleported')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('appointments.data', 0));
    }
}
