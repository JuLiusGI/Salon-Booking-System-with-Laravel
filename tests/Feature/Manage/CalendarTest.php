<?php

namespace Tests\Feature\Manage;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use BuildsSalonSchedule, RefreshDatabase;

    // A Tuesday, so the surrounding week is unambiguous.
    private const DATE = '2026-09-15';

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

    /**
     * @return array<string, array{0: string}>
     */
    public static function diaryPages(): array
    {
        return [
            'calendar' => ['/manage/calendar'],
            'appointment list' => ['/manage/appointments'],
        ];
    }

    #[DataProvider('diaryPages')]
    public function test_salon_staff_can_open_the_diary_and_customers_cannot(string $uri): void
    {
        foreach ([UserRole::Admin, UserRole::Receptionist] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get($uri)->assertOk();
        }

        // A stylist reaches the diary too, but sees only their own work.
        $this->actingAs($this->rosteredStylist()->user)->get($uri)->assertOk();
    }

    #[DataProvider('diaryPages')]
    public function test_a_guest_is_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    /* Views ------------------------------------------------------------------ */

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function views(): array
    {
        return [
            'day shows one day' => ['day', 1],
            'week shows seven days' => ['week', 7],
        ];
    }

    #[DataProvider('views')]
    public function test_each_view_spans_the_right_number_of_days(string $view, int $expected): void
    {
        $this->actingAs($this->desk())
            ->get("/manage/calendar?view={$view}&date=".self::DATE)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/Calendar')
                ->where('view', $view)
                ->has('days', $expected)
            );
    }

    public function test_the_month_view_is_padded_to_whole_weeks(): void
    {
        $this->actingAs($this->desk())
            ->get('/manage/calendar?view=month&date='.self::DATE)
            ->assertInertia(function (Assert $page) {
                $page->where('view', 'month');

                // Always a whole number of weeks, so the grid has no ragged edge.
                $days = $page->toArray()['props']['days'];
                $this->assertSame(0, count($days) % 7);
                $this->assertGreaterThanOrEqual(28, count($days));

                return $page;
            });
    }

    public function test_an_unknown_view_falls_back_to_the_week(): void
    {
        $this->actingAs($this->desk())
            ->get('/manage/calendar?view=decade')
            ->assertInertia(fn (Assert $page) => $page->where('view', 'week')->has('days', 7));
    }

    public function test_a_bad_date_falls_back_to_today_rather_than_erroring(): void
    {
        $this->actingAs($this->desk())
            ->get('/manage/calendar?date=not-a-date')
            ->assertOk();
    }

    /* Contents ---------------------------------------------------------------- */

    public function test_the_calendar_shows_the_appointments_actually_booked(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $this->actingAs($this->desk())
            ->get('/manage/calendar?view=day&date='.self::DATE)
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments', 1)
                ->where('appointments.0.start_label', '11:00 AM')
                ->where('appointments.0.date', self::DATE)
                // Minutes from salon midnight, so the frontend positions blocks
                // without reparsing dates or guessing a timezone.
                ->where('appointments.0.start_minute', 11 * 60)
                ->where('appointments.0.end_minute', 12 * 60)
            );
    }

    public function test_appointments_outside_the_range_are_not_included(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)->at($this->local(self::DATE.' 11:00'), 60)->create();
        Appointment::factory()->forStaff($staff)->at($this->local('2026-10-20 11:00'), 60)->create();

        $this->actingAs($this->desk())
            ->get('/manage/calendar?view=day&date='.self::DATE)
            ->assertInertia(fn (Assert $page) => $page->has('appointments', 1));
    }

    public function test_a_cancelled_appointment_is_shown_but_marked_as_not_holding_the_slot(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)
            ->at($this->local(self::DATE.' 11:00'), 60)
            ->cancelled()
            ->create();

        $this->actingAs($this->desk())
            ->get('/manage/calendar?view=day&date='.self::DATE)
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments', 1)
                ->where('appointments.0.holds_slot', false)
            );
    }

    /* Filters -------------------------------------------------------------------- */

    public function test_the_calendar_can_be_filtered_by_stylist(): void
    {
        $first = $this->rosteredStylist();
        $second = $this->rosteredStylist();

        Appointment::factory()->forStaff($first)->at($this->local(self::DATE.' 10:00'), 60)->create();
        Appointment::factory()->forStaff($second)->at($this->local(self::DATE.' 12:00'), 60)->create();

        $this->actingAs($this->desk())
            ->get('/manage/calendar?view=day&date='.self::DATE."&staff={$first->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments', 1)
                ->where('appointments.0.staff_id', $first->id)
            );
    }

    public function test_the_calendar_can_be_filtered_by_status(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)->at($this->local(self::DATE.' 10:00'), 60)
            ->status(AppointmentStatus::Confirmed)->create();
        Appointment::factory()->forStaff($staff)->at($this->local(self::DATE.' 12:00'), 60)
            ->status(AppointmentStatus::Pending)->create();

        $this->actingAs($this->desk())
            ->get('/manage/calendar?view=day&date='.self::DATE.'&status=pending')
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments', 1)
                ->where('appointments.0.status', 'pending')
            );
    }

    /* Visibility ------------------------------------------------------------------- */

    public function test_a_stylist_sees_only_their_own_work_in_the_calendar(): void
    {
        $mine = $this->rosteredStylist();
        $theirs = $this->rosteredStylist();

        Appointment::factory()->forStaff($mine)->at($this->local(self::DATE.' 10:00'), 60)->create();
        Appointment::factory()->forStaff($theirs)->at($this->local(self::DATE.' 12:00'), 60)->create();

        $this->actingAs($mine->user)
            ->get('/manage/calendar?view=day&date='.self::DATE)
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments', 1)
                ->where('appointments.0.staff_id', $mine->id)
            );
    }

    public function test_a_stylist_sees_only_their_own_work_in_the_list(): void
    {
        $mine = $this->rosteredStylist();
        $theirs = $this->rosteredStylist();

        Appointment::factory()->forStaff($mine)->count(2)->create();
        Appointment::factory()->forStaff($theirs)->count(3)->create();

        $this->actingAs($mine->user)
            ->get('/manage/appointments')
            ->assertInertia(fn (Assert $page) => $page->has('appointments.data', 2));
    }

    public function test_a_stylist_cannot_widen_the_view_by_filtering_to_another_stylist(): void
    {
        $mine = $this->rosteredStylist();
        $theirs = $this->rosteredStylist();

        Appointment::factory()->forStaff($theirs)->count(3)->create();

        // The filter is applied on top of the visibility scope, never instead
        // of it, so asking for someone else returns nothing.
        $this->actingAs($mine->user)
            ->get("/manage/appointments?staff={$theirs->id}")
            ->assertInertia(fn (Assert $page) => $page->has('appointments.data', 0));
    }

    public function test_a_stylist_is_only_offered_themselves_in_the_stylist_filter(): void
    {
        $mine = $this->rosteredStylist();
        $this->rosteredStylist();

        $this->actingAs($mine->user)
            ->get('/manage/appointments')
            ->assertInertia(fn (Assert $page) => $page
                ->has('staff', 1)
                ->where('staff.0.value', $mine->id)
            );
    }

    public function test_the_desk_sees_every_stylists_work(): void
    {
        $first = $this->rosteredStylist();
        $second = $this->rosteredStylist();

        Appointment::factory()->forStaff($first)->count(2)->create();
        Appointment::factory()->forStaff($second)->count(3)->create();

        $this->actingAs($this->desk())
            ->get('/manage/appointments')
            ->assertInertia(fn (Assert $page) => $page->has('appointments.data', 5));
    }

    /* List filters --------------------------------------------------------------- */

    public function test_the_list_can_be_searched_by_reference(): void
    {
        $wanted = Appointment::factory()->create(['reference' => 'SB-FINDME1234']);
        Appointment::factory()->count(3)->create();

        $this->actingAs($this->desk())
            ->get('/manage/appointments?search=FINDME')
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments.data', 1)
                ->where('appointments.data.0.reference', $wanted->reference)
            );
    }

    public function test_the_list_can_be_filtered_by_date_range(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-15 10:00'), 60)->create();
        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-25 10:00'), 60)->create();

        $this->actingAs($this->desk())
            ->get('/manage/appointments?from=2026-09-20&to=2026-09-30')
            ->assertInertia(fn (Assert $page) => $page->has('appointments.data', 1));
    }
}
