<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScheduleExceptionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingRuleRequest;
use App\Http\Requests\Admin\SalonHoursRequest;
use App\Http\Requests\Admin\ScheduleExceptionRequest;
use App\Http\Requests\Admin\StaffScheduleRequest;
use App\Models\BookingRule;
use App\Models\SalonHour;
use App\Models\ScheduleException;
use App\Models\Staff;
use App\Models\StaffAvailability;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin management of everything the availability engine reads.
 *
 * Times are entered and displayed in salon wall-clock. Recurring hours are stored
 * as wall-clock because that is what they mean; one-off exceptions are converted
 * to UTC instants on the way in, because they refer to a specific moment.
 */
class ScheduleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /* Salon opening hours -------------------------------------------------- */

    public function editHours(): Response
    {
        $existing = SalonHour::query()->get()->keyBy('day_of_week');

        $days = collect(range(0, 6))->map(function (int $day) use ($existing) {
            $row = $existing->get($day);

            return [
                'day_of_week' => $day,
                'is_closed' => $row?->is_closed ?? true,
                'opens_at' => $row && ! $row->is_closed ? substr((string) $row->opens_at, 0, 5) : '09:00',
                'closes_at' => $row && ! $row->is_closed ? substr((string) $row->closes_at, 0, 5) : '17:00',
            ];
        });

        return Inertia::render('Admin/Schedule/Hours', [
            'days' => $days,
            'timezone' => config('salon.timezone'),
        ]);
    }

    public function updateHours(SalonHoursRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('days') as $day) {
                $closed = (bool) $day['is_closed'];

                SalonHour::updateOrCreate(
                    ['day_of_week' => (int) $day['day_of_week']],
                    [
                        'is_closed' => $closed,
                        'opens_at' => $closed ? null : $day['opens_at'],
                        'closes_at' => $closed ? null : $day['closes_at'],
                    ],
                );
            }
        });

        $this->audit->record('salon_hours.updated');

        return back()->with('success', 'Opening hours updated.');
    }

    /* Booking rules --------------------------------------------------------- */

    public function editRules(): Response
    {
        $rules = BookingRule::current();

        return Inertia::render('Admin/Schedule/Rules', [
            'rules' => [
                'min_advance_minutes' => $rules->min_advance_minutes,
                'max_advance_days' => $rules->max_advance_days,
                'cancellation_deadline_hours' => $rules->cancellation_deadline_hours,
                'reschedule_deadline_hours' => $rules->reschedule_deadline_hours,
                'buffer_minutes' => $rules->buffer_minutes,
                'slot_interval_minutes' => $rules->slot_interval_minutes,
                'max_duration_minutes' => $rules->max_duration_minutes,
            ],
        ]);
    }

    public function updateRules(BookingRuleRequest $request): RedirectResponse
    {
        $rules = BookingRule::query()->orderBy('id')->first() ?? new BookingRule;

        $rules->fill($request->validated());
        $rules->save();

        $this->audit->record('booking_rules.updated', $rules, $request->validated());

        return back()->with('success', 'Booking rules updated.');
    }

    /* Staff working hours ---------------------------------------------------- */

    public function editStaffSchedule(Staff $staff): Response
    {
        $this->authorize('update', $staff);

        $staff->load('user:id,name');

        return Inertia::render('Admin/Schedule/StaffSchedule', [
            'member' => [
                'id' => $staff->id,
                'name' => $staff->user->name,
                'is_active' => $staff->is_active,
                'is_bookable' => $staff->is_bookable,
            ],
            'blocks' => $staff->availabilities()
                ->orderBy('day_of_week')
                ->orderBy('starts_at')
                ->get()
                ->map(fn (StaffAvailability $block) => [
                    'day_of_week' => $block->day_of_week,
                    'starts_at' => substr((string) $block->starts_at, 0, 5),
                    'ends_at' => substr((string) $block->ends_at, 0, 5),
                    'is_active' => $block->is_active,
                ]),
            'timezone' => config('salon.timezone'),
        ]);
    }

    public function updateStaffSchedule(StaffScheduleRequest $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        DB::transaction(function () use ($request, $staff) {
            // Replacing wholesale keeps the stored shifts exactly what the admin
            // sees, rather than leaving orphaned rows behind a partial update.
            $staff->availabilities()->delete();

            foreach ($request->validated('blocks') as $block) {
                StaffAvailability::create([
                    'staff_id' => $staff->id,
                    'day_of_week' => (int) $block['day_of_week'],
                    'starts_at' => $block['starts_at'],
                    'ends_at' => $block['ends_at'],
                    'is_active' => (bool) $block['is_active'],
                ]);
            }
        });

        $this->audit->record('staff_schedule.updated', $staff, ['name' => $staff->user->name]);

        return back()->with('success', "Working hours updated for {$staff->user->name}.");
    }

    /* Exceptions -------------------------------------------------------------- */

    public function exceptions(Request $request): Response
    {
        $timezone = config('salon.timezone');

        $exceptions = ScheduleException::query()
            ->with('staff.user:id,name')
            ->when($request->integer('staff'), fn ($query, int $id) => $query->where('staff_id', $id))
            ->when($request->string('type')->toString(), fn ($query, string $type) => $query->where('type', $type))
            // Upcoming first: what the salon is about to be affected by matters
            // more than what already happened.
            ->where('ends_at', '>=', CarbonImmutable::now()->subMonth())
            ->orderBy('starts_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ScheduleException $exception) => [
                'id' => $exception->id,
                'type' => $exception->type,
                'type_label' => $exception->type->label(),
                'staff_name' => $exception->staff?->user->name,
                'starts_at' => $exception->starts_at->setTimezone($timezone)->format('Y-m-d H:i'),
                'ends_at' => $exception->ends_at->setTimezone($timezone)->format('Y-m-d H:i'),
                'override_opens_at' => $exception->override_opens_at
                    ? substr((string) $exception->override_opens_at, 0, 5)
                    : null,
                'override_closes_at' => $exception->override_closes_at
                    ? substr((string) $exception->override_closes_at, 0, 5)
                    : null,
                'reason' => $exception->reason,
                'is_past' => $exception->ends_at->isPast(),
            ]);

        return Inertia::render('Admin/Schedule/Exceptions', [
            'exceptions' => $exceptions,
            'filters' => $request->only('staff', 'type'),
            'staff' => $this->staffOptions(),
            'types' => collect(ScheduleExceptionType::cases())
                ->map(fn (ScheduleExceptionType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'salon_wide' => $type->isSalonWide() || $type === ScheduleExceptionType::SpecialHours,
                ]),
            'timezone' => config('salon.timezone'),
        ]);
    }

    public function storeException(ScheduleExceptionRequest $request): RedirectResponse
    {
        $exception = ScheduleException::create([
            'staff_id' => $request->input('staff_id') ?: null,
            'type' => $request->type(),
            'starts_at' => $request->startsAt(),
            'ends_at' => $request->endsAt(),
            'override_opens_at' => $request->input('override_opens_at'),
            'override_closes_at' => $request->input('override_closes_at'),
            'reason' => $request->input('reason'),
        ]);

        $this->audit->record('schedule_exception.created', $exception, [
            'type' => $exception->type->value,
        ]);

        return back()->with('success', $exception->type->label().' added to the schedule.');
    }

    public function destroyException(ScheduleException $exception): RedirectResponse
    {
        $label = $exception->type->label();

        $exception->delete();

        $this->audit->record('schedule_exception.deleted', null, ['type' => $exception->type->value]);

        return back()->with('success', "{$label} removed from the schedule.");
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    private function staffOptions(): Collection
    {
        return Staff::query()
            ->active()
            ->with('user:id,name')
            ->orderBy('display_order')
            ->get()
            ->map(fn (Staff $member) => [
                'value' => $member->id,
                'label' => $member->user->name,
            ]);
    }
}
