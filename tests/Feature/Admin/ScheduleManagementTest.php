<?php

namespace Tests\Feature\Admin;

use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use App\Models\BookingRule;
use App\Models\SalonHour;
use App\Models\ScheduleException;
use App\Models\StaffAvailability;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSalonSchedule;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use BuildsSalonSchedule, RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dayPayload(array $overrides = []): array
    {
        return collect(range(0, 6))
            ->map(fn (int $day) => array_merge([
                'day_of_week' => $day,
                'is_closed' => false,
                'opens_at' => '09:00',
                'closes_at' => '17:00',
            ], $overrides[$day] ?? []))
            ->all();
    }

    /* Access ---------------------------------------------------------------- */

    /**
     * @return array<string, array{0: string}>
     */
    public static function schedulePages(): array
    {
        return [
            'opening hours' => ['/admin/schedule/hours'],
            'booking rules' => ['/admin/schedule/rules'],
            'exceptions' => ['/admin/schedule/exceptions'],
        ];
    }

    #[DataProvider('schedulePages')]
    public function test_only_an_admin_can_manage_the_schedule(string $uri): void
    {
        foreach (UserRole::cases() as $role) {
            $response = $this->actingAs(User::factory()->role($role)->create())->get($uri);

            $role === UserRole::Admin ? $response->assertOk() : $response->assertForbidden();
        }
    }

    #[DataProvider('schedulePages')]
    public function test_a_guest_is_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    public function test_a_stylist_cannot_edit_their_own_working_hours_here(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($staff->user)
            ->get("/admin/staff/{$staff->id}/schedule")
            ->assertForbidden();

        $this->actingAs($staff->user)
            ->put("/admin/staff/{$staff->id}/schedule", ['blocks' => []])
            ->assertForbidden();
    }

    /* Opening hours --------------------------------------------------------- */

    public function test_an_admin_can_set_the_weekly_opening_hours(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/schedule/hours', [
                'days' => $this->dayPayload([
                    0 => ['is_closed' => true, 'opens_at' => null, 'closes_at' => null],
                    4 => ['closes_at' => '20:00'],
                ]),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('salon_hours', ['day_of_week' => 4, 'closes_at' => '20:00:00']);

        $sunday = SalonHour::where('day_of_week', 0)->firstOrFail();

        $this->assertTrue($sunday->is_closed);
        $this->assertNull($sunday->opens_at);
    }

    public function test_a_closing_time_before_opening_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/schedule/hours', [
                'days' => $this->dayPayload([2 => ['opens_at' => '17:00', 'closes_at' => '09:00']]),
            ])
            ->assertSessionHasErrors('days.2.closes_at');
    }

    public function test_an_open_day_without_times_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/schedule/hours', [
                'days' => $this->dayPayload([3 => ['opens_at' => null, 'closes_at' => null]]),
            ])
            ->assertSessionHasErrors('days.3.opens_at');
    }

    public function test_changing_opening_hours_immediately_changes_availability(): void
    {
        $this->travelTo($this->local('2026-09-01 08:00'));
        $this->openSalon('09:00', '17:00');
        $this->bookingRules();

        $staff = $this->rosteredStylist('08:00', '20:00');
        $service = $this->serviceFor($staff, 60);

        $engine = app(AvailabilityService::class);
        $before = $engine->slotsFor($staff, collect([$service]), $this->localDate('2026-09-15'));

        $this->actingAs($this->admin())->put('/admin/schedule/hours', [
            'days' => $this->dayPayload([]),
        ]);

        $this->actingAs($this->admin())->put('/admin/schedule/hours', [
            'days' => $this->dayPayload(array_fill(0, 7, ['opens_at' => '10:00', 'closes_at' => '12:00'])),
        ])->assertSessionHasNoErrors();

        $after = $engine->slotsFor($staff, collect([$service]), $this->localDate('2026-09-15'));

        $this->assertGreaterThan($after->count(), $before->count());
        $this->assertSame('10:00', $this->labels($after)[0]);
    }

    /* Booking rules ---------------------------------------------------------- */

    public function test_an_admin_can_update_the_booking_rules(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/schedule/rules', [
                'min_advance_minutes' => 120,
                'max_advance_days' => 30,
                'cancellation_deadline_hours' => 48,
                'reschedule_deadline_hours' => 12,
                'buffer_minutes' => 20,
                'slot_interval_minutes' => 30,
                'max_duration_minutes' => 300,
            ])
            ->assertSessionHasNoErrors();

        $rules = BookingRule::current();

        $this->assertSame(120, $rules->min_advance_minutes);
        $this->assertSame(20, $rules->buffer_minutes);
        $this->assertSame(300, $rules->max_duration_minutes);
    }

    public function test_only_one_booking_rules_row_is_ever_kept(): void
    {
        $admin = $this->admin();

        foreach ([15, 30, 45] as $interval) {
            $this->actingAs($admin)->put('/admin/schedule/rules', [
                'min_advance_minutes' => 60,
                'max_advance_days' => 60,
                'cancellation_deadline_hours' => 24,
                'reschedule_deadline_hours' => 24,
                'buffer_minutes' => 0,
                'slot_interval_minutes' => $interval,
                'max_duration_minutes' => null,
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('booking_rules', 1);
        $this->assertSame(45, BookingRule::current()->slot_interval_minutes);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidRules(): array
    {
        return [
            'no booking horizon' => [['max_advance_days' => 0], 'max_advance_days'],
            'interval too small' => [['slot_interval_minutes' => 1], 'slot_interval_minutes'],
            'negative buffer' => [['buffer_minutes' => -5], 'buffer_minutes'],
            'max shorter than a slot' => [
                ['slot_interval_minutes' => 60, 'max_duration_minutes' => 30],
                'max_duration_minutes',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidRules')]
    public function test_invalid_booking_rules_are_rejected(array $overrides, string $field): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/schedule/rules', array_merge([
                'min_advance_minutes' => 60,
                'max_advance_days' => 60,
                'cancellation_deadline_hours' => 24,
                'reschedule_deadline_hours' => 24,
                'buffer_minutes' => 0,
                'slot_interval_minutes' => 15,
                'max_duration_minutes' => null,
            ], $overrides))
            ->assertSessionHasErrors($field);
    }

    /* Staff working hours ------------------------------------------------------ */

    public function test_an_admin_can_replace_a_staff_members_shifts(): void
    {
        $staff = $this->rosteredStylist();

        $this->assertSame(7, $staff->availabilities()->count());

        $this->actingAs($this->admin())
            ->put("/admin/staff/{$staff->id}/schedule", [
                'blocks' => [
                    ['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '13:00', 'is_active' => true],
                    ['day_of_week' => 2, 'starts_at' => '14:00', 'ends_at' => '18:00', 'is_active' => true],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $staff->availabilities()->count());
        $this->assertDatabaseHas('staff_availabilities', [
            'staff_id' => $staff->id,
            'day_of_week' => 2,
            'starts_at' => '14:00:00',
        ]);
    }

    public function test_overlapping_shifts_on_the_same_day_are_rejected(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($this->admin())
            ->put("/admin/staff/{$staff->id}/schedule", [
                'blocks' => [
                    ['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '13:00', 'is_active' => true],
                    ['day_of_week' => 2, 'starts_at' => '12:00', 'ends_at' => '18:00', 'is_active' => true],
                ],
            ])
            ->assertSessionHasErrors('blocks.0.starts_at');
    }

    public function test_the_same_hours_on_different_days_are_not_an_overlap(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($this->admin())
            ->put("/admin/staff/{$staff->id}/schedule", [
                'blocks' => [
                    ['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00', 'is_active' => true],
                    ['day_of_week' => 3, 'starts_at' => '09:00', 'ends_at' => '17:00', 'is_active' => true],
                ],
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_shift_ending_before_it_starts_is_rejected(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($this->admin())
            ->put("/admin/staff/{$staff->id}/schedule", [
                'blocks' => [
                    ['day_of_week' => 2, 'starts_at' => '17:00', 'ends_at' => '09:00', 'is_active' => true],
                ],
            ])
            ->assertSessionHasErrors('blocks.0.ends_at');
    }

    public function test_all_shifts_can_be_cleared(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($this->admin())
            ->put("/admin/staff/{$staff->id}/schedule", ['blocks' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, StaffAvailability::query()->where('staff_id', $staff->id)->count());
    }

    /* Exceptions ---------------------------------------------------------------- */

    public function test_an_admin_can_record_leave_for_a_staff_member(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($this->admin())
            ->post('/admin/schedule/exceptions', [
                'staff_id' => $staff->id,
                'type' => ScheduleExceptionType::Leave->value,
                'starts_at' => '2026-09-15 00:00',
                'ends_at' => '2026-09-17 23:59',
                'reason' => 'Annual leave',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_exceptions', [
            'staff_id' => $staff->id,
            'type' => ScheduleExceptionType::Leave->value,
        ]);
    }

    public function test_submitted_times_are_read_as_salon_wall_clock_not_utc(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($this->admin())->post('/admin/schedule/exceptions', [
            'staff_id' => $staff->id,
            'type' => ScheduleExceptionType::Break->value,
            'starts_at' => '2026-09-15 12:00',
            'ends_at' => '2026-09-15 13:00',
        ])->assertSessionHasNoErrors();

        $exception = ScheduleException::query()->latest('id')->firstOrFail();

        // Noon in Manila is 04:00 UTC. Storing 12:00 UTC would push the break
        // eight hours out and silently block the wrong slots.
        $this->assertSame('2026-09-15 04:00', $exception->starts_at->format('Y-m-d H:i'));
        $this->assertSame(
            '12:00',
            $exception->starts_at->setTimezone($this->salonTimezone())->format('H:i'),
        );
    }

    public function test_a_holiday_cannot_be_attached_to_one_staff_member(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($this->admin())
            ->post('/admin/schedule/exceptions', [
                'staff_id' => $staff->id,
                'type' => ScheduleExceptionType::Holiday->value,
                'starts_at' => '2026-09-15 00:00',
                'ends_at' => '2026-09-15 23:59',
            ])
            ->assertSessionHasErrors('staff_id');
    }

    public function test_a_break_without_a_staff_member_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/schedule/exceptions', [
                'staff_id' => null,
                'type' => ScheduleExceptionType::Break->value,
                'starts_at' => '2026-09-15 12:00',
                'ends_at' => '2026-09-15 13:00',
            ])
            ->assertSessionHasErrors('staff_id');
    }

    public function test_an_exception_ending_before_it_starts_is_rejected(): void
    {
        $staff = $this->rosteredStylist();

        $this->actingAs($this->admin())
            ->post('/admin/schedule/exceptions', [
                'staff_id' => $staff->id,
                'type' => ScheduleExceptionType::Break->value,
                'starts_at' => '2026-09-15 13:00',
                'ends_at' => '2026-09-15 12:00',
            ])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_special_hours_require_replacement_times(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/schedule/exceptions', [
                'staff_id' => null,
                'type' => ScheduleExceptionType::SpecialHours->value,
                'starts_at' => '2026-09-15 00:00',
                'ends_at' => '2026-09-15 23:59',
            ])
            ->assertSessionHasErrors('override_opens_at');
    }

    public function test_special_hours_are_stored_with_their_replacement_times(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/schedule/exceptions', [
                'staff_id' => null,
                'type' => ScheduleExceptionType::SpecialHours->value,
                'starts_at' => '2026-12-24 00:00',
                'ends_at' => '2026-12-24 23:59',
                'override_opens_at' => '09:00',
                'override_closes_at' => '13:00',
                'reason' => 'Christmas Eve',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_exceptions', [
            'type' => ScheduleExceptionType::SpecialHours->value,
            'override_opens_at' => '09:00:00',
            'override_closes_at' => '13:00:00',
        ]);
    }

    public function test_removing_an_exception_frees_the_time_again(): void
    {
        $this->travelTo($this->local('2026-09-01 08:00'));
        $this->openSalon();
        $this->bookingRules();

        $staff = $this->rosteredStylist();
        $service = $this->serviceFor($staff, 60);

        $break = $this->blockStaff($staff, '2026-09-15 12:00', '2026-09-15 13:00');

        $engine = app(AvailabilityService::class);

        $this->assertNotContains(
            '12:00',
            $this->labels($engine->slotsFor($staff, collect([$service]), $this->localDate('2026-09-15'))),
        );

        $this->actingAs($this->admin())
            ->delete("/admin/schedule/exceptions/{$break->id}")
            ->assertSessionHasNoErrors();

        $this->assertContains(
            '12:00',
            $this->labels($engine->slotsFor($staff, collect([$service]), $this->localDate('2026-09-15'))),
        );
    }

    public function test_schedule_changes_are_written_to_the_audit_log(): void
    {
        $staff = $this->rosteredStylist();
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/schedule/hours', ['days' => $this->dayPayload()]);
        $this->actingAs($admin)->put("/admin/staff/{$staff->id}/schedule", ['blocks' => []]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'salon_hours.updated', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_schedule.updated', 'user_id' => $admin->id]);
    }
}
