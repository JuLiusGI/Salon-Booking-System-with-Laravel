<?php

namespace App\Http\Controllers\Manage;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingRuleChecker;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The salon's own view of its appointments.
 *
 * Every list runs through Appointment::visibleTo(), so a stylist sees only their
 * own work without each query having to remember that rule.
 */
class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentStatusService $statuses,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Appointment::class);

        $timezone = config('salon.timezone');
        $user = $request->user();

        $appointments = Appointment::query()
            ->visibleTo($user)
            ->with(['customer:id,name,email', 'staff.user:id,name', 'items'])
            ->when($request->integer('staff'), fn ($q, int $id) => $q->where('staff_id', $id))
            ->when($request->string('status')->toString(), fn ($q, string $s) => $q->where('status', $s))
            ->when($request->integer('service'), fn ($q, int $id) => $q->whereHas(
                'items',
                fn ($i) => $i->where('service_id', $id),
            ))
            ->when($request->string('from')->toString(), fn ($q, string $d) => $q->where(
                'starts_at',
                '>=',
                CarbonImmutable::parse($d, $timezone)->startOfDay()->utc(),
            ))
            ->when($request->string('to')->toString(), fn ($q, string $d) => $q->where(
                'starts_at',
                '<=',
                CarbonImmutable::parse($d, $timezone)->endOfDay()->utc(),
            ))
            ->when($request->string('search')->toString(), function ($q, string $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('reference', 'like', '%'.$term.'%')
                        ->orWhereHas('customer', function ($c) use ($term) {
                            $c->where('name', 'like', '%'.$term.'%')
                                ->orWhere('email', 'like', '%'.$term.'%');
                        });
                });
            })
            ->orderByDesc('starts_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Appointment $appointment) => $this->row($appointment));

        return Inertia::render('Manage/Appointments/Index', [
            'appointments' => $appointments,
            'filters' => $request->only('staff', 'status', 'service', 'from', 'to', 'search'),
            'staff' => $this->staffOptions($user),
            'services' => $this->serviceOptions(),
            'statuses' => $this->statusOptions(),
            'timezone' => $timezone,
        ]);
    }

    public function show(Request $request, Appointment $appointment): Response
    {
        $this->authorize('view', $appointment);

        $appointment->load(['customer.customerProfile', 'staff.user:id,name', 'items', 'rescheduledFrom']);

        $user = $request->user();
        $rules = new BookingRuleChecker;
        $timezone = config('salon.timezone');

        return Inertia::render('Manage/Appointments/Show', [
            'appointment' => array_merge($this->row($appointment), [
                'internal_notes' => $appointment->internal_notes,
                'notes' => $appointment->notes,
                'source' => $appointment->source,
                'booked_on' => $appointment->created_at->setTimezone($timezone)->format('j M Y, g:i A'),
                'checked_in_at' => $appointment->checked_in_at?->setTimezone($timezone)->format('j M Y, g:i A'),
                'started_at' => $appointment->started_at?->setTimezone($timezone)->format('j M Y, g:i A'),
                'completed_at' => $appointment->completed_at?->setTimezone($timezone)->format('j M Y, g:i A'),
                'cancelled_at' => $appointment->cancelled_at?->setTimezone($timezone)->format('j M Y, g:i A'),
                'cancellation_reason' => $appointment->cancellation_reason,
                'rescheduled_from' => $appointment->rescheduledFrom?->reference,

                // Customer detail the salon needs in the chair, and no more.
                'customer' => [
                    'name' => $appointment->customer->name,
                    'email' => $appointment->customer->email,
                    'phone' => $appointment->customer->phone,
                    'allergies' => $appointment->customer->customerProfile?->allergies,
                    'preferences' => $appointment->customer->customerProfile?->preferences,
                ],
            ]),

            // Only moves this user could actually make, so the interface never
            // offers a button the server would refuse.
            'available_transitions' => collect($this->statuses->availableTransitions($appointment, $user))
                ->map(fn (AppointmentStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),

            'can' => [
                'update' => $user->can('update', $appointment),
                'cancel' => $user->can('cancel', $appointment),
                'reschedule' => $user->can('reschedule', $appointment),
            ],

            'deadlines' => [
                'cancel_by' => $rules->cancellationDeadlineFor($appointment)
                    ->setTimezone($timezone)->format('j M Y, g:i A'),
                'reschedule_by' => $rules->reschedulingDeadlineFor($appointment)
                    ->setTimezone($timezone)->format('j M Y, g:i A'),
            ],

            'timezone' => $timezone,
        ]);
    }

    /**
     * Editing is limited to the internal notes.
     *
     * Changing services would change the duration and therefore the slot, so
     * that goes through rescheduling rather than a quiet edit.
     */
    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $validated = $request->validate([
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $appointment->internal_notes = $validated['internal_notes'] ?? null;
        $appointment->save();

        $this->audit->record('appointment.notes_updated', $appointment, [
            'reference' => $appointment->reference,
        ]);

        return back()->with('success', 'Notes saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Appointment $appointment): array
    {
        $timezone = config('salon.timezone');
        $start = $appointment->starts_at->setTimezone($timezone);
        $end = $appointment->ends_at->setTimezone($timezone);

        return [
            'reference' => $appointment->reference,
            'status' => $appointment->status,
            'status_label' => $appointment->status->label(),
            'date' => $start->format('D, j M Y'),
            'time' => $start->format('g:i A').' - '.$end->format('g:i A'),
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'customer_name' => $appointment->customer->name,
            'staff_name' => $appointment->staff->user->name,
            'total_duration_minutes' => $appointment->total_duration_minutes,
            'total_price' => $appointment->total_price,
            'services' => $appointment->items->pluck('service_name')->all(),
            'is_past' => $appointment->ends_at->isPast(),
        ];
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    private function staffOptions(User $user): Collection
    {
        // A stylist filtering by staff can only ever mean themselves.
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

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function statusOptions(): Collection
    {
        return collect(AppointmentStatus::cases())->map(fn (AppointmentStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);
    }
}
