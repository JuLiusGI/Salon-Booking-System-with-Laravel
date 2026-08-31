<?php

namespace Tests\Feature\Reporting;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\User;
use App\Services\Reporting\SalonMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

class DashboardAndReportsTest extends TestCase
{
    use BuildsSalonSchedule, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo($this->local('2026-09-15 08:00'));
        $this->openSalon();
        $this->bookingRules();
    }

    private function metrics(): SalonMetrics
    {
        return app(SalonMetrics::class);
    }

    /* Who sees which dashboard ------------------------------------------------ */

    public function test_each_role_lands_on_the_dashboard_built_for_it(): void
    {
        $expected = [
            UserRole::Admin->value => 'Dashboard/Desk',
            UserRole::Receptionist->value => 'Dashboard/Desk',
            UserRole::Stylist->value => 'Dashboard/Stylist',
            UserRole::Customer->value => 'Dashboard/Customer',
        ];

        foreach ($expected as $role => $component) {
            $user = $role === UserRole::Stylist->value
                ? $this->rosteredStylist()->user
                : User::factory()->role(UserRole::from($role))->create();

            $this->actingAs($user)
                ->get('/dashboard')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_only_the_desk_can_open_reports(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = $role === UserRole::Stylist
                ? $this->rosteredStylist()->user
                : User::factory()->role($role)->create();

            $response = $this->actingAs($user)->get('/manage/reports');

            in_array($role, [UserRole::Admin, UserRole::Receptionist], true)
                ? $response->assertOk()
                : $response->assertForbidden();
        }
    }

    public function test_a_guest_cannot_open_reports_or_the_dashboard(): void
    {
        $this->get('/manage/reports')->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /* Metrics reconcile against the database ------------------------------------- */

    public function test_status_counts_match_the_rows_in_the_table(): void
    {
        $staff = $this->rosteredStylist();
        $from = $this->local('2026-09-15 00:00');
        $to = $this->local('2026-09-15 23:59');

        $plan = [
            AppointmentStatus::Completed->value => 4,
            AppointmentStatus::Cancelled->value => 2,
            AppointmentStatus::NoShow->value => 1,
            AppointmentStatus::Confirmed->value => 3,
        ];

        foreach ($plan as $status => $count) {
            Appointment::factory()->forStaff($staff)->count($count)
                ->at($this->local('2026-09-15 10:00'), 60)
                ->status(AppointmentStatus::from($status))
                ->create();
        }

        $counts = $this->metrics()->statusCounts($from, $to);

        foreach ($plan as $status => $expected) {
            $this->assertSame($expected, $counts[$status], "count for {$status}");
        }

        $this->assertSame(array_sum($plan), array_sum($counts));
        $this->assertSame(Appointment::query()->count(), array_sum($counts));
    }

    public function test_completed_value_matches_the_sum_of_those_appointments(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-15 10:00'), 60)
            ->status(AppointmentStatus::Completed)->create(['total_price' => 750.00]);
        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-15 12:00'), 60)
            ->status(AppointmentStatus::Completed)->create(['total_price' => 1250.50]);

        // Cancelled work was never carried out, so it must not be counted.
        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-15 14:00'), 60)
            ->cancelled()->create(['total_price' => 9999.00]);

        $value = $this->metrics()->value(
            $this->local('2026-09-15 00:00'),
            $this->local('2026-09-15 23:59'),
        );

        $this->assertSame('2000.50', $value['completed_value']);
        $this->assertSame(2, $value['completed_count']);
    }

    public function test_attrition_rates_are_the_share_of_all_appointments(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)->count(6)
            ->at($this->local('2026-09-15 10:00'), 60)
            ->status(AppointmentStatus::Completed)->create();
        Appointment::factory()->forStaff($staff)->count(3)
            ->at($this->local('2026-09-15 10:00'), 60)->cancelled()->create();
        Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-15 10:00'), 60)->noShow()->create();

        $rates = $this->metrics()->attritionRates(
            $this->local('2026-09-15 00:00'),
            $this->local('2026-09-15 23:59'),
        );

        $this->assertSame(10, $rates['total']);
        $this->assertSame(30.0, $rates['cancellation_rate']);
        $this->assertSame(10.0, $rates['no_show_rate']);
    }

    public function test_the_daily_trend_has_no_gaps_and_counts_the_right_days(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)->count(2)
            ->at($this->local('2026-09-10 10:00'), 60)->create();
        Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-12 10:00'), 60)->create();

        $trend = $this->metrics()->dailyTrend(
            $this->local('2026-09-09 00:00'),
            $this->local('2026-09-13 23:59'),
        );

        // Every day in the range appears, so a quiet day reads as zero rather
        // than vanishing from the series.
        $this->assertCount(5, $trend);

        $byDate = collect($trend)->keyBy('date');

        $this->assertSame(0, $byDate['2026-09-09']['total']);
        $this->assertSame(2, $byDate['2026-09-10']['total']);
        $this->assertSame(0, $byDate['2026-09-11']['total']);
        $this->assertSame(1, $byDate['2026-09-12']['total']);
    }

    public function test_a_late_evening_appointment_is_counted_on_its_salon_local_day(): void
    {
        $staff = $this->rosteredStylist();

        // 23:00 in Manila is 15:00 UTC the same day. Grouping in UTC would be
        // fine here, but 00:30 Manila is 16:30 UTC the *previous* day, which is
        // the case that catches a naive implementation.
        Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-11 00:30'), 60)
            ->create();

        $trend = collect($this->metrics()->dailyTrend(
            $this->local('2026-09-09 00:00'),
            $this->local('2026-09-13 23:59'),
        ))->keyBy('date');

        $this->assertSame(1, $trend['2026-09-11']['total'], 'must be filed under the salon-local date');
        $this->assertSame(0, $trend['2026-09-10']['total']);
    }

    public function test_peak_hours_are_read_in_salon_time(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)->count(3)
            ->at($this->local('2026-09-15 09:00'), 60)->create();

        $peaks = $this->metrics()->peakPeriods(
            $this->local('2026-09-15 00:00'),
            $this->local('2026-09-15 23:59'),
        );

        $byHour = collect($peaks['by_hour'])->keyBy('label');

        // 09:00 salon time, not 01:00 which is the stored UTC hour.
        $this->assertSame(3, $byHour['09:00']['total']);
        $this->assertArrayNotHasKey('01:00', $byHour->all());
    }

    public function test_popular_services_read_from_the_booked_snapshot(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60, 750.00);
        $bookedAs = $service->name;

        foreach (range(1, 3) as $i) {
            $appointment = Appointment::factory()->forStaff($staff)
                ->at($this->local('2026-09-15 10:00'), 60)
                ->status(AppointmentStatus::Completed)
                ->create();

            AppointmentItem::factory()->forService($service)->create([
                'appointment_id' => $appointment->id,
            ]);
        }

        // Renaming afterwards must not rewrite what was booked.
        $service->update(['name' => 'Renamed Later']);

        $popular = $this->metrics()->popularServices(
            $this->local('2026-09-15 00:00'),
            $this->local('2026-09-15 23:59'),
        );

        $this->assertSame($bookedAs, $popular[0]['name']);
        $this->assertSame(3, $popular[0]['bookings']);
    }

    public function test_cancelled_work_is_excluded_from_popularity(): void
    {
        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $cancelled = Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-15 10:00'), 60)->cancelled()->create();

        AppointmentItem::factory()->forService($service)->create(['appointment_id' => $cancelled->id]);

        $popular = $this->metrics()->popularServices(
            $this->local('2026-09-15 00:00'),
            $this->local('2026-09-15 23:59'),
        );

        $this->assertSame([], $popular);
    }

    public function test_staff_statistics_add_up_per_person(): void
    {
        $first = $this->rosteredStylist();
        $second = $this->rosteredStylist();

        Appointment::factory()->forStaff($first)->count(3)
            ->at($this->local('2026-09-15 10:00'), 60)
            ->status(AppointmentStatus::Completed)->create();
        Appointment::factory()->forStaff($first)
            ->at($this->local('2026-09-15 10:00'), 60)->noShow()->create();
        Appointment::factory()->forStaff($second)->count(2)
            ->at($this->local('2026-09-15 10:00'), 60)
            ->status(AppointmentStatus::Completed)->create();

        $stats = collect($this->metrics()->staffPerformance(
            $this->local('2026-09-15 00:00'),
            $this->local('2026-09-15 23:59'),
        ))->keyBy('name');

        $this->assertSame(4, $stats[$first->user->name]['total']);
        $this->assertSame(3, $stats[$first->user->name]['completed']);
        $this->assertSame(1, $stats[$first->user->name]['no_show']);
        $this->assertSame(75.0, $stats[$first->user->name]['completion_rate']);
        $this->assertSame(2, $stats[$second->user->name]['total']);
    }

    /* The report page ---------------------------------------------------------------- */

    public function test_the_report_covers_whole_days_even_when_run_at_midday(): void
    {
        $staff = $this->rosteredStylist();

        // Later today, so an unnormalised range ending at "now" would miss it.
        Appointment::factory()->forStaff($staff)
            ->at($this->local('2026-09-15 16:00'), 60)
            ->status(AppointmentStatus::Confirmed)
            ->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/manage/reports?from=2026-09-15&to=2026-09-15')
            ->assertInertia(fn (Assert $page) => $page
                ->where('range.days', 1)
                ->where('attrition.total', 1)
            );
    }

    public function test_a_backwards_range_is_treated_as_a_typo_rather_than_nothing(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/manage/reports?from=2026-09-20&to=2026-09-10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('range.from', '2026-09-10')
                ->where('range.to', '2026-09-20')
            );
    }

    public function test_a_nonsense_date_falls_back_rather_than_erroring(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/manage/reports?from=banana&to=also-banana')
            ->assertOk();
    }

    public function test_the_dashboard_counts_today_correctly(): void
    {
        $staff = $this->rosteredStylist();

        Appointment::factory()->forStaff($staff)->count(2)
            ->at($this->local('2026-09-15 10:00'), 60)
            ->status(AppointmentStatus::Confirmed)->create();

        // Yesterday and tomorrow are not today.
        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-14 10:00'), 60)->create();
        Appointment::factory()->forStaff($staff)->at($this->local('2026-09-16 10:00'), 60)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.total', 2)
                ->has('schedule', 2)
            );
    }

    public function test_the_dashboard_flags_services_nobody_can_perform(): void
    {
        // Active but with no staff assigned, so it can never actually be booked.
        Service::factory()->create(['is_active' => true]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('attention.services_without_staff', 1));
    }

    public function test_a_stylist_dashboard_shows_only_their_own_day(): void
    {
        $mine = $this->rosteredStylist();
        $theirs = $this->rosteredStylist();

        Appointment::factory()->forStaff($mine)->count(2)
            ->at($this->local('2026-09-15 10:00'), 60)->create();
        Appointment::factory()->forStaff($theirs)->count(3)
            ->at($this->local('2026-09-15 10:00'), 60)->create();

        $this->actingAs($mine->user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->has('today.schedule', 2));
    }

    public function test_nothing_on_the_reports_page_is_labelled_as_revenue(): void
    {
        $response = $this->actingAs(User::factory()->receptionist()->create())->get('/manage/reports');

        // The system takes no payments, so it must never claim money received
        // (MASTER_SPEC section 14).
        $response->assertDontSee('revenue received', escape: false);
        $response->assertDontSee('Revenue', escape: false);
    }
}
