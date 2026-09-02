<?php

namespace Tests\Feature\Security;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

/**
 * Query budgets for the list screens.
 *
 * The point is not the exact number but its shape: it must not grow with the
 * number of rows. Each test loads a small page, then a much larger one, and
 * fails if the count climbed with the data — which is what an N+1 does.
 */
class QueryBudgetTest extends TestCase
{
    use BuildsSalonSchedule, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo($this->local('2026-09-01 08:00'));
        $this->openSalon();
        $this->bookingRules();
    }

    private function countQueries(User $actor, string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($actor)->get($url)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function seedAppointments(int $count): void
    {
        $staff = $this->rosteredStylist();

        for ($i = 0; $i < $count; $i++) {
            Appointment::factory()
                ->forStaff($staff)
                ->at($this->local('2026-09-02 09:00')->addMinutes($i * 15), 15)
                ->create();
        }
    }

    public function test_the_appointment_list_does_not_query_per_row(): void
    {
        $desk = User::factory()->receptionist()->create();

        $this->seedAppointments(3);
        $small = $this->countQueries($desk, '/manage/appointments');

        $this->seedAppointments(20);
        $large = $this->countQueries($desk, '/manage/appointments');

        $this->assertSame(
            $small,
            $large,
            "The appointment list ran {$small} queries for 3 rows and {$large} for 23: the count grows per row.",
        );
    }

    public function test_the_customer_directory_does_not_query_per_row(): void
    {
        $desk = User::factory()->receptionist()->create();

        User::factory()->count(3)->create();
        $small = $this->countQueries($desk, '/manage/customers');

        User::factory()->count(20)->create();
        $large = $this->countQueries($desk, '/manage/customers');

        $this->assertSame($small, $large, "Customer directory: {$small} queries for 3, {$large} for 23.");
    }

    public function test_the_user_directory_does_not_query_per_row(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->count(3)->create();
        $small = $this->countQueries($admin, '/admin/users');

        User::factory()->count(15)->create();
        $large = $this->countQueries($admin, '/admin/users');

        $this->assertSame($small, $large, "User directory: {$small} queries for 3, {$large} for 18.");
    }

    public function test_the_public_service_menu_does_not_query_per_service(): void
    {
        $staff = $this->rosteredStylist();

        // The baseline must already have rows. With none at all Laravel skips
        // the eager-load query outright, and an empty page is not a fair
        // comparison against a full one.
        for ($i = 0; $i < 2; $i++) {
            $this->serviceFor($staff, 30);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/services')->assertOk();
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();

        for ($i = 0; $i < 12; $i++) {
            $this->serviceFor($staff, 30);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/services')->assertOk();
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($small, $large, "Service menu: {$small} queries for few, {$large} for many.");
    }
}
