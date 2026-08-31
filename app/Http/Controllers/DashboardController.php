<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Services\Reporting\SalonMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in landing page.
 *
 * Three different jobs share one route, because "what should I do next" means
 * something different to each role. The desk gets the operational picture, a
 * stylist gets their own day, and a customer gets their own bookings.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly SalonMetrics $metrics) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return match (true) {
            $user->isAdmin() || $user->hasRole(UserRole::Receptionist) => $this->desk($user),
            $user->hasRole(UserRole::Stylist) => $this->stylist($user),
            default => $this->customer($user),
        };
    }

    private function desk($user): Response
    {
        $to = CarbonImmutable::now();
        $from = $to->subDays(29)->setTimezone(config('salon.timezone'))->startOfDay();

        return Inertia::render('Dashboard/Desk', [
            'today' => $this->metrics->today(),
            'attention' => $this->metrics->attention(),
            'totals' => $this->metrics->totals(),
            'schedule' => $this->metrics->todaysSchedule(),

            'month' => [
                'value' => $this->metrics->value($from, $to),
                'attrition' => $this->metrics->attritionRates($from, $to),
            ],

            'trend' => $this->metrics->dailyTrend($from, $to),
            'timezone' => config('salon.timezone'),
        ]);
    }

    private function stylist($user): Response
    {
        $now = CarbonImmutable::now();

        return Inertia::render('Dashboard/Stylist', [
            'today' => [
                'date' => $now->setTimezone(config('salon.timezone'))->format('l, j F Y'),
                'schedule' => $this->metrics->todaysSchedule($user),
            ],

            'upcoming_count' => Appointment::query()
                ->visibleTo($user)
                ->blocking()
                ->whereBetween('starts_at', [$now, $now->addWeek()])
                ->count(),

            'timezone' => config('salon.timezone'),
        ]);
    }

    private function customer($user): Response
    {
        $now = CarbonImmutable::now();
        $timezone = config('salon.timezone');

        $upcoming = Appointment::query()
            ->where('customer_id', $user->getKey())
            ->blocking()
            ->where('ends_at', '>=', $now)
            ->with(['staff.user:id,name', 'items'])
            ->orderBy('starts_at')
            ->limit(3)
            ->get()
            ->map(fn (Appointment $appointment) => [
                'reference' => $appointment->reference,
                'date' => $appointment->starts_at->setTimezone($timezone)->format('l, j F Y'),
                'time' => $appointment->starts_at->setTimezone($timezone)->format('g:i A'),
                'staff_name' => $appointment->staff->user->name,
                'services' => $appointment->items->pluck('service_name')->all(),
                'status' => $appointment->status,
                'status_label' => $appointment->status->label(),
            ]);

        return Inertia::render('Dashboard/Customer', [
            'upcoming' => $upcoming,
            'visits' => Appointment::query()
                ->where('customer_id', $user->getKey())
                ->where('status', AppointmentStatus::Completed)
                ->count(),
            'timezone' => $timezone,
        ]);
    }
}
