<?php

namespace App\Services\Reporting;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the dashboard and reports count.
 *
 * Two rules run through this class.
 *
 * Nothing here is revenue. The system takes no payments, so money is only ever
 * described as the value of work booked or completed (MASTER_SPEC section 14).
 *
 * Anything grouped by day or hour is grouped in PHP against the salon's
 * timezone, not in SQL. Appointments are stored in UTC, so grouping in the
 * database would file a 9am Manila appointment under the previous day, and
 * hard-coding an offset would break the moment the salon's timezone changed.
 * Plain counts, which do not care about calendar boundaries, stay in SQL.
 */
class SalonMetrics
{
    private string $timezone;

    public function __construct()
    {
        $this->timezone = config('salon.timezone');
    }

    /* Operational ------------------------------------------------------------ */

    /**
     * Headline numbers for today.
     *
     * @return array<string, mixed>
     */
    public function today(): array
    {
        $today = CarbonImmutable::now($this->timezone);
        $from = $today->startOfDay()->utc();
        $to = $today->endOfDay()->utc();

        $counts = $this->statusCounts($from, $to);

        return [
            'date' => $today->format('l, j F Y'),
            'total' => array_sum($counts),
            'by_status' => $counts,
            'remaining' => Appointment::query()
                ->whereBetween('starts_at', [CarbonImmutable::now()->utc(), $to])
                ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
                ->count(),
            'checked_in' => $counts[AppointmentStatus::CheckedIn->value] ?? 0,
            'in_progress' => $counts[AppointmentStatus::InProgress->value] ?? 0,
        ];
    }

    /**
     * Things waiting on someone, which is what a quick-actions panel is for.
     *
     * @return array<string, int>
     */
    public function attention(): array
    {
        $now = CarbonImmutable::now();

        return [
            'awaiting_confirmation' => Appointment::query()
                ->where('status', AppointmentStatus::Pending)
                ->where('starts_at', '>=', $now)
                ->count(),

            'upcoming_week' => Appointment::query()
                ->blocking()
                ->whereBetween('starts_at', [$now, $now->addWeek()])
                ->count(),

            // Still sitting in a live status after the appointment ended, so
            // somebody needs to close them off.
            'unresolved_past' => Appointment::query()
                ->whereIn('status', [
                    AppointmentStatus::Pending,
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::CheckedIn,
                    AppointmentStatus::InProgress,
                ])
                ->where('ends_at', '<', $now)
                ->count(),

            'services_without_staff' => Service::query()
                ->active()
                ->whereDoesntHave('staff')
                ->count(),
        ];
    }

    /**
     * Today's schedule, as a list.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function todaysSchedule(?User $viewer = null): Collection
    {
        $today = CarbonImmutable::now($this->timezone);

        return Appointment::query()
            ->when($viewer, fn ($q) => $q->visibleTo($viewer))
            ->with(['customer:id,name', 'staff.user:id,name', 'items'])
            ->whereBetween('starts_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment) => [
                'reference' => $appointment->reference,
                'time' => $appointment->starts_at->setTimezone($this->timezone)->format('g:i A'),
                'customer_name' => $appointment->customer->name,
                'staff_name' => $appointment->staff->user->name,
                'services' => $appointment->items->pluck('service_name')->all(),
                'status' => $appointment->status,
                'status_label' => $appointment->status->label(),
            ]);
    }

    /* Totals ------------------------------------------------------------------- */

    /**
     * @return array<string, int>
     */
    public function totals(): array
    {
        return [
            'customers' => User::query()->where('role', UserRole::Customer)->count(),
            'active_staff' => Staff::query()->active()->count(),
            'bookable_staff' => Staff::query()->bookable()->count(),
            'active_services' => Service::query()->active()->count(),
        ];
    }

    /* Analytical ---------------------------------------------------------------- */

    /**
     * Counts by status across a period.
     *
     * @return array<string, int>
     */
    public function statusCounts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Appointment::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereBetween('starts_at', [$from, $to])
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];

        foreach (AppointmentStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $counts;
    }

    /**
     * The value of work booked and of work actually completed.
     *
     * Neither is revenue: no payment is ever recorded, so these describe the
     * diary, not the till.
     *
     * @return array<string, string|int>
     */
    public function value(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $booked = Appointment::query()
            ->blocking()
            ->whereBetween('starts_at', [$from, $to]);

        $completed = Appointment::query()
            ->where('status', AppointmentStatus::Completed)
            ->whereBetween('starts_at', [$from, $to]);

        return [
            'booked_value' => number_format((float) $booked->sum('total_price'), 2, '.', ''),
            'completed_value' => number_format((float) $completed->sum('total_price'), 2, '.', ''),
            'booked_count' => (clone $booked)->count(),
            'completed_count' => (clone $completed)->count(),
        ];
    }

    /**
     * How often bookings fall through.
     *
     * @return array<string, float|int>
     */
    public function attritionRates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = $this->statusCounts($from, $to);
        $total = array_sum($counts);

        $rate = fn (int $n) => $total > 0 ? round($n / $total * 100, 1) : 0.0;

        return [
            'total' => $total,
            'cancelled' => $counts[AppointmentStatus::Cancelled->value],
            'no_show' => $counts[AppointmentStatus::NoShow->value],
            'cancellation_rate' => $rate($counts[AppointmentStatus::Cancelled->value]),
            'no_show_rate' => $rate($counts[AppointmentStatus::NoShow->value]),
        ];
    }

    /**
     * Appointments per day, with no gaps, so a quiet day reads as zero rather
     * than disappearing from the series.
     *
     * @return list<array{date: string, label: string, total: int, completed: int}>
     */
    public function dailyTrend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $appointments = Appointment::query()
            ->select(['starts_at', 'status'])
            ->whereBetween('starts_at', [$from, $to])
            ->get();

        $byDate = [];

        foreach ($appointments as $appointment) {
            $date = $appointment->starts_at->setTimezone($this->timezone)->toDateString();

            $byDate[$date] ??= ['total' => 0, 'completed' => 0];
            $byDate[$date]['total']++;

            if ($appointment->status === AppointmentStatus::Completed) {
                $byDate[$date]['completed']++;
            }
        }

        $series = [];
        $cursor = $from->setTimezone($this->timezone)->startOfDay();
        $end = $to->setTimezone($this->timezone)->startOfDay();

        while ($cursor <= $end) {
            $date = $cursor->toDateString();

            $series[] = [
                'date' => $date,
                'label' => $cursor->format('j M'),
                'total' => $byDate[$date]['total'] ?? 0,
                'completed' => $byDate[$date]['completed'] ?? 0,
            ];

            $cursor = $cursor->addDay();
        }

        return $series;
    }

    /**
     * When the salon is busiest, by hour and by weekday.
     *
     * @return array{by_hour: list<array{label: string, total: int}>, by_weekday: list<array{label: string, total: int}>}
     */
    public function peakPeriods(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $appointments = Appointment::query()
            ->select(['starts_at'])
            ->blocking()
            ->whereBetween('starts_at', [$from, $to])
            ->get();

        $byHour = array_fill(0, 24, 0);
        $byWeekday = array_fill(0, 7, 0);

        foreach ($appointments as $appointment) {
            $local = $appointment->starts_at->setTimezone($this->timezone);

            $byHour[$local->hour]++;
            $byWeekday[$local->dayOfWeek]++;
        }

        // Only the hours a salon could plausibly trade, so the chart is not
        // mostly empty overnight.
        $hours = [];

        for ($hour = 7; $hour <= 21; $hour++) {
            $hours[] = [
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                'total' => $byHour[$hour],
            ];
        }

        $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $weekdays = [];

        // Monday first, matching the calendar.
        foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
            $weekdays[] = ['label' => $names[$day], 'total' => $byWeekday[$day]];
        }

        return ['by_hour' => $hours, 'by_weekday' => $weekdays];
    }

    /**
     * Most-booked services, read from the appointment item snapshots so a
     * renamed or withdrawn service still appears as it was booked.
     *
     * @return list<array<string, mixed>>
     */
    public function popularServices(CarbonImmutable $from, CarbonImmutable $to, int $limit = 8): array
    {
        return \DB::table('appointment_items as i')
            ->join('appointments as a', 'a.id', '=', 'i.appointment_id')
            ->whereBetween('a.starts_at', [$from, $to])
            ->whereNotIn('a.status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->groupBy('i.service_name')
            ->orderByDesc('bookings')
            ->limit($limit)
            ->get([
                'i.service_name as name',
                \DB::raw('COUNT(*) as bookings'),
                \DB::raw('SUM(i.service_price) as value'),
            ])
            ->map(fn ($row) => [
                'name' => $row->name,
                'bookings' => (int) $row->bookings,
                'value' => number_format((float) $row->value, 2, '.', ''),
            ])
            ->all();
    }

    /**
     * How each category is performing.
     *
     * Joined through the live service, so an item whose service was hard
     * deleted is excluded rather than counted under a missing category.
     *
     * @return list<array<string, mixed>>
     */
    public function categoryPerformance(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return \DB::table('appointment_items as i')
            ->join('appointments as a', 'a.id', '=', 'i.appointment_id')
            ->join('services as s', 's.id', '=', 'i.service_id')
            ->join('service_categories as c', 'c.id', '=', 's.service_category_id')
            ->whereBetween('a.starts_at', [$from, $to])
            ->whereNotIn('a.status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('bookings')
            ->get([
                'c.name as name',
                \DB::raw('COUNT(*) as bookings'),
                \DB::raw('SUM(i.service_price) as value'),
            ])
            ->map(fn ($row) => [
                'name' => $row->name,
                'bookings' => (int) $row->bookings,
                'value' => number_format((float) $row->value, 2, '.', ''),
            ])
            ->all();
    }

    /**
     * Per-stylist statistics.
     *
     * @return list<array<string, mixed>>
     */
    public function staffPerformance(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Appointment::query()
            ->selectRaw('staff_id, status, COUNT(*) as total, SUM(total_price) as value')
            ->whereBetween('starts_at', [$from, $to])
            ->groupBy('staff_id', 'status')
            ->get()
            ->groupBy('staff_id');

        return Staff::query()
            ->active()
            ->with('user:id,name')
            ->orderBy('display_order')
            ->get()
            ->map(function (Staff $member) use ($rows) {
                $theirs = $rows->get($member->id, collect());

                $count = fn (AppointmentStatus $status) => (int) ($theirs
                    ->firstWhere('status', $status->value)->total ?? 0);

                $completed = $count(AppointmentStatus::Completed);
                $total = (int) $theirs->sum('total');

                return [
                    'name' => $member->user->name,
                    'total' => $total,
                    'completed' => $completed,
                    'cancelled' => $count(AppointmentStatus::Cancelled),
                    'no_show' => $count(AppointmentStatus::NoShow),
                    'completion_rate' => $total > 0 ? round($completed / $total * 100, 1) : 0.0,
                    'completed_value' => number_format(
                        (float) ($theirs->firstWhere('status', AppointmentStatus::Completed->value)->value ?? 0),
                        2, '.', ''
                    ),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * New customer accounts per day across the period.
     *
     * @return list<array{date: string, label: string, total: int}>
     */
    public function customerGrowth(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $created = User::query()
            ->where('role', UserRole::Customer)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('created_at');

        $byDate = [];

        foreach ($created as $at) {
            $date = CarbonImmutable::instance($at)->setTimezone($this->timezone)->toDateString();
            $byDate[$date] = ($byDate[$date] ?? 0) + 1;
        }

        $series = [];
        $cursor = $from->setTimezone($this->timezone)->startOfDay();
        $end = $to->setTimezone($this->timezone)->startOfDay();

        while ($cursor <= $end) {
            $date = $cursor->toDateString();

            $series[] = [
                'date' => $date,
                'label' => $cursor->format('j M'),
                'total' => $byDate[$date] ?? 0,
            ];

            $cursor = $cursor->addDay();
        }

        return $series;
    }
}
