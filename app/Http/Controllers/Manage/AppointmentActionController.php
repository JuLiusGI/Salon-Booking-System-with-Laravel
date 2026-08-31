<?php

namespace App\Http\Controllers\Manage;

use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentTransitionException;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Availability\AvailabilityService;
use App\Services\Booking\BookingRuleChecker;
use App\Services\Booking\RescheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acting on an appointment: moving its status, cancelling it, or moving it.
 *
 * Shared by staff and customers. What each may do is decided by
 * AppointmentPolicy rather than by which screen the request came from, so a
 * customer cannot reach a staff-only action by posting to a different URL.
 */
class AppointmentActionController extends Controller
{
    public function __construct(
        private readonly AppointmentStatusService $statuses,
        private readonly RescheduleService $rescheduler,
        private readonly AvailabilityService $availability,
    ) {}

    /**
     * Move an appointment to another status: check-in, start, complete, no-show.
     */
    public function transition(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $target = AppointmentStatus::from($validated['status']);

        // Authorises the specific move, not merely "may touch this appointment".
        $this->authorize('transition', [$appointment, $target]);

        try {
            $this->statuses->transition(
                $appointment,
                $target,
                $request->user(),
                $validated['reason'] ?? null,
            );
        } catch (AppointmentTransitionException $exception) {
            throw $exception->toValidationException();
        }

        return back()->with('success', "Appointment marked as {$target->label()}.");
    }

    /**
     * Cancel. A customer is held to the notice period; the desk is not, because
     * a phone call is a legitimate way to cancel late.
     */
    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('cancel', $appointment);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->statuses->transition(
                $appointment,
                AppointmentStatus::Cancelled,
                $request->user(),
                $validated['reason'] ?? null,
            );
        } catch (AppointmentTransitionException $exception) {
            throw $exception->toValidationException();
        }

        return back()->with('success', 'The appointment has been cancelled and the time released.');
    }

    /**
     * The reschedule screen, showing what is free for the same services.
     */
    public function editSchedule(Request $request, Appointment $appointment): Response
    {
        $this->authorize('reschedule', $appointment);

        $appointment->load(['items', 'staff.user:id,name', 'customer:id,name']);

        $rules = new BookingRuleChecker;
        $timezone = config('salon.timezone');
        $services = $this->rescheduler->servicesFor($appointment);

        $staff = $this->resolveStaff($request) ?? $appointment->staff;
        $date = $this->resolveDate($request);

        return Inertia::render('Manage/Appointments/Reschedule', [
            'appointment' => [
                'reference' => $appointment->reference,
                'customer_name' => $appointment->customer->name,
                'staff_name' => $appointment->staff->user->name,
                'current' => $appointment->starts_at->setTimezone($timezone)->format('D, j M Y \a\t g:i A'),
                'duration_minutes' => $appointment->total_duration_minutes,
                'services' => $appointment->items->pluck('service_name')->all(),
            ],

            // Empty when a service has since left the menu, which the page
            // explains rather than silently rebooking something different.
            'services_resolvable' => $services->isNotEmpty(),

            'stylists' => $this->stylistsFor($services),
            'selection' => ['staff_id' => $staff?->id, 'date' => $date?->toDateString()],

            'slots' => Inertia::optional(function () use ($staff, $services, $date, $rules) {
                if ($staff === null || $services->isEmpty() || $date === null) {
                    return ['date' => $date?->toDateString(), 'times' => []];
                }

                return [
                    'date' => $date->toDateString(),
                    'times' => $this->availability
                        // Ignoring this appointment stops it blocking its own move.
                        ->slotsFor($staff, $services, $date, $rules)
                        ->map(fn ($slot) => $slot->toArray())
                        ->all(),
                ];
            }),

            'booking_window' => [
                'earliest_date' => $rules->earliestStart()->setTimezone($timezone)->toDateString(),
                'latest_date' => $rules->latestStart()->setTimezone($timezone)->toDateString(),
            ],

            'timezone' => $timezone,
        ]);
    }

    public function reschedule(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('reschedule', $appointment);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'staff_id' => [
                'nullable', 'integer',
                Rule::exists('staff', 'id')
                    ->where('is_active', true)
                    ->where('is_bookable', true)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $staff = isset($validated['staff_id'])
            ? Staff::query()->whereKey($validated['staff_id'])->first()
            : null;

        try {
            $replacement = $this->rescheduler->reschedule(
                $appointment,
                CarbonImmutable::parse($validated['starts_at'])->utc(),
                $request->user(),
                $staff,
            );
        } catch (BookingException $exception) {
            throw $exception->toValidationException();
        }

        $route = $request->user()->isCustomer() ? 'appointments.show' : 'manage.appointments.show';

        return redirect()
            ->route($route, $replacement->reference)
            ->with('success', 'The appointment has been moved. Your new reference is '.$replacement->reference.'.');
    }

    private function resolveStaff(Request $request): ?Staff
    {
        $id = $request->integer('staff_id');

        return $id ? Staff::query()->bookable()->whereKey($id)->first() : null;
    }

    private function resolveDate(Request $request): ?CarbonImmutable
    {
        $date = $request->string('date')->toString();

        if ($date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date, config('salon.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, Service>  $services
     * @return Collection<int, array<string, mixed>>
     */
    private function stylistsFor($services)
    {
        $query = Staff::query()->bookable()->with('user:id,name');

        foreach ($services as $service) {
            $query->whereHas('services', fn ($q) => $q->whereKey($service->getKey()));
        }

        return $query->orderBy('display_order')->get()->map(fn (Staff $member) => [
            'id' => $member->id,
            'name' => $member->user->name,
            'title' => $member->title,
        ]);
    }
}
