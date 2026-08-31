<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Services\Booking\BookingRuleChecker;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A customer's own appointments: what is coming up, what has happened, and the
 * confirmation page they land on straight after booking.
 */
class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Appointment::class);

        $user = $request->user();
        $now = CarbonImmutable::now();

        $base = Appointment::query()
            ->where('customer_id', $user->getKey())
            ->with(['staff.user:id,name', 'items']);

        return Inertia::render('Appointments/Index', [
            'upcoming' => (clone $base)
                ->where('ends_at', '>=', $now)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Appointment $appointment) => $this->summarise($appointment))
                ->values(),

            'past' => (clone $base)
                ->where('ends_at', '<', $now)
                ->orderByDesc('starts_at')
                ->limit(50)
                ->get()
                ->map(fn (Appointment $appointment) => $this->summarise($appointment))
                ->values(),

            'timezone' => config('salon.timezone'),
        ]);
    }

    public function show(Request $request, Appointment $appointment): Response
    {
        $this->authorize('view', $appointment);

        $appointment->load(['staff.user:id,name', 'items', 'customer:id,name,email']);

        $rules = new BookingRuleChecker;
        $timezone = config('salon.timezone');

        return Inertia::render('Appointments/Show', [
            'appointment' => array_merge($this->summarise($appointment), [
                'notes' => $appointment->notes,
                'customer_name' => $appointment->customer->name,
                'booked_on' => $appointment->created_at->setTimezone($timezone)->format('j M Y'),

                // Shown so the customer knows how long they have to change
                // their mind, even though acting on it arrives in a later phase.
                'cancellation_deadline' => $rules->cancellationDeadlineFor($appointment)
                    ->setTimezone($timezone)->format('j M Y, g:i A'),
                'reschedule_deadline' => $rules->reschedulingDeadlineFor($appointment)
                    ->setTimezone($timezone)->format('j M Y, g:i A'),
                'can_still_cancel' => $rules->allowsCancellation($appointment),
                'can_still_reschedule' => $rules->allowsRescheduling($appointment),
            ]),
            'timezone' => $timezone,
        ]);
    }

    /**
     * The shape both the list and the detail page read.
     *
     * Deliberately narrow: no qr_token, no internal notes, nothing about other
     * customers (MASTER_SPEC section 17).
     *
     * @return array<string, mixed>
     */
    private function summarise(Appointment $appointment): array
    {
        $timezone = config('salon.timezone');
        $start = $appointment->starts_at->setTimezone($timezone);
        $end = $appointment->ends_at->setTimezone($timezone);

        return [
            'reference' => $appointment->reference,
            'status' => $appointment->status,
            'status_label' => $appointment->status->label(),
            'is_upcoming' => $appointment->ends_at->isFuture(),
            'blocks_availability' => $appointment->status->blocksAvailability(),

            'date' => $start->format('l, j F Y'),
            'time' => $start->format('g:i A').' - '.$end->format('g:i A'),
            'starts_at' => $appointment->starts_at->toIso8601String(),

            'staff_name' => $appointment->staff->user->name,
            'total_duration_minutes' => $appointment->total_duration_minutes,
            'total_price' => $appointment->total_price,

            'items' => $this->items($appointment->items),
        ];
    }

    /**
     * @param  Collection<int, AppointmentItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function items(Collection $items): array
    {
        // Read from the snapshot columns, never from the live service, so a
        // past appointment keeps the price and duration it was booked at.
        return $items->map(fn (AppointmentItem $item) => [
            'name' => $item->service_name,
            'price' => $item->service_price,
            'duration_minutes' => $item->service_duration_minutes,
        ])->values()->all();
    }
}
