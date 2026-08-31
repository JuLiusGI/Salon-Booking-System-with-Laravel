<?php

namespace App\Http\Controllers\Manage;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Day, week, and month views of the diary.
 *
 * The calendar reads the same appointments table as every other view, through
 * the same visibility scope. There is no separate cache or projection to fall out
 * of step with what was actually booked.
 */
class CalendarController extends Controller
{
    private const VIEWS = ['day', 'week', 'month'];

    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', Appointment::class);

        $timezone = config('salon.timezone');
        $user = $request->user();

        $view = in_array($request->string('view')->toString(), self::VIEWS, true)
            ? $request->string('view')->toString()
            : 'week';

        $anchor = $this->anchorDate($request, $timezone);
        [$from, $to] = $this->range($anchor, $view);

        $appointments = Appointment::query()
            ->visibleTo($user)
            ->with(['customer:id,name', 'staff.user:id,name', 'items'])
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->when($request->integer('staff'), fn ($q, int $id) => $q->where('staff_id', $id))
            ->when($request->string('status')->toString(), fn ($q, string $s) => $q->where('status', $s))
            ->when($request->integer('service'), fn ($q, int $id) => $q->whereHas(
                'items',
                fn ($i) => $i->where('service_id', $id),
            ))
            ->orderBy('starts_at')
            ->get()
            ->map(function (Appointment $appointment) use ($timezone) {
                $start = $appointment->starts_at->setTimezone($timezone);
                $end = $appointment->ends_at->setTimezone($timezone);

                return [
                    'reference' => $appointment->reference,
                    'status' => $appointment->status,
                    'status_label' => $appointment->status->label(),

                    // The day it falls on in salon time, which is what groups it
                    // into the right column.
                    'date' => $start->toDateString(),
                    'start_label' => $start->format('g:i A'),
                    'end_label' => $end->format('g:i A'),

                    // Minutes from midnight, so the frontend can position a block
                    // without reparsing dates or guessing a timezone.
                    'start_minute' => $start->hour * 60 + $start->minute,
                    'end_minute' => $end->hour * 60 + $end->minute,

                    'customer_name' => $appointment->customer->name,
                    'staff_name' => $appointment->staff->user->name,
                    'staff_id' => $appointment->staff_id,
                    'services' => $appointment->items->pluck('service_name')->all(),
                    'holds_slot' => $appointment->status->blocksAvailability(),
                ];
            });

        return Inertia::render('Manage/Calendar', [
            'view' => $view,
            'anchor' => $anchor->toDateString(),
            'range' => [
                'from' => $from->toDateString(),
                'to' => $from->toDateString() === $to->toDateString()
                    ? $to->toDateString()
                    : $to->subSecond()->toDateString(),
                'label' => $this->rangeLabel($anchor, $view),
            ],
            'days' => $this->days($from, $to),
            'appointments' => $appointments,
            'filters' => $request->only('staff', 'status', 'service'),
            'staff' => $this->staffOptions($user),
            'services' => $this->serviceOptions(),
            'statuses' => collect(AppointmentStatus::cases())->map(fn (AppointmentStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'timezone' => $timezone,
        ]);
    }

    private function anchorDate(Request $request, string $timezone): CarbonImmutable
    {
        $date = $request->string('date')->toString();

        if ($date === '') {
            return CarbonImmutable::now($timezone)->startOfDay();
        }

        try {
            return CarbonImmutable::parse($date, $timezone)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::now($timezone)->startOfDay();
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(CarbonImmutable $anchor, string $view): array
    {
        return match ($view) {
            'day' => [$anchor, $anchor->addDay()],
            'month' => [
                // Padded to whole weeks so the grid has no ragged edges.
                // endOfWeek() carries microseconds, so the end is normalised to
                // the following midnight rather than nudged by a second, which
                // would spill one extra day into the grid.
                $anchor->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY),
                $anchor->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()->addDay(),
            ],
            default => [
                $anchor->startOfWeek(CarbonImmutable::MONDAY),
                $anchor->startOfWeek(CarbonImmutable::MONDAY)->addWeek(),
            ],
        };
    }

    private function rangeLabel(CarbonImmutable $anchor, string $view): string
    {
        return match ($view) {
            'day' => $anchor->format('l, j F Y'),
            'month' => $anchor->format('F Y'),
            default => $anchor->startOfWeek(CarbonImmutable::MONDAY)->format('j M')
                .' - '.$anchor->endOfWeek(CarbonImmutable::SUNDAY)->format('j M Y'),
        };
    }

    /**
     * Every date in the range, so the frontend renders empty days rather than
     * skipping them.
     *
     * @return list<array{date: string, label: string, weekday: string, is_today: bool, in_month: bool}>
     */
    private function days(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $today = CarbonImmutable::now(config('salon.timezone'))->toDateString();
        $days = [];
        $cursor = $from;

        while ($cursor < $to) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('j'),
                'weekday' => $cursor->format('D'),
                'is_today' => $cursor->toDateString() === $today,
                'in_month' => $cursor->month === $from->addDays(15)->month,
            ];

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    private function staffOptions(User $user): Collection
    {
        $query = Staff::query()->active()->with('user:id,name');

        if (! $user->isAdmin() && ! $user->hasRole(UserRole::Receptionist)) {
            $query->whereKey($user->staff?->getKey() ?? 0);
        }

        return $query->orderBy('display_order')->get()->map(fn (Staff $member) => [
            'value' => $member->id,
            'label' => $member->user->name,
        ]);
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    private function serviceOptions(): Collection
    {
        return Service::query()->ordered()->get(['id', 'name'])->map(fn (Service $service) => [
            'value' => $service->id,
            'label' => $service->name,
        ]);
    }
}
