<?php

namespace App\Http\Controllers\Manage;

use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Qr\AppointmentQrService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Front-desk check-in, by scanned code or by typing a reference.
 *
 * The QR is a shortcut, never a credential. Resolving one requires an
 * authenticated staff session and the same policy check as any other route, so a
 * scanned code in the wrong hands opens nothing (MASTER_SPEC section 20).
 *
 * Everything here works without a code too: the reference lookup is the fallback,
 * and it is the same lookup the salon would use if a phone battery died.
 */
class CheckInController extends Controller
{
    public function __construct(
        private readonly AppointmentQrService $qr,
        private readonly AppointmentStatusService $statuses,
    ) {}

    /**
     * The desk screen: today's arrivals, plus a box to type a reference into.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewDiary', Appointment::class);

        $timezone = config('salon.timezone');
        $today = CarbonImmutable::now($timezone);

        $arrivals = Appointment::query()
            ->visibleTo($request->user())
            ->with(['customer:id,name', 'staff.user:id,name', 'items'])
            ->whereBetween('starts_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()])
            ->whereIn('status', [
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::CheckedIn,
                AppointmentStatus::InProgress,
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment) => $this->summarise($appointment, $request));

        return Inertia::render('Manage/CheckIn', [
            'arrivals' => $arrivals,
            'today' => $today->format('l, j F Y'),
            'timezone' => $timezone,
            'lookup' => $request->string('reference')->toString(),
            'found' => $this->lookupByReference($request),
        ]);
    }

    /**
     * Resolve a scanned code.
     *
     * An unknown token and a token belonging to an appointment this user may not
     * see both produce the same not-found result, so scanning cannot be used to
     * probe for valid codes.
     */
    public function resolve(Request $request, string $token): Response
    {
        $this->authorize('viewDiary', Appointment::class);

        $appointment = $this->qr->resolve($token);

        if ($appointment === null || $request->user()->cannot('view', $appointment)) {
            return Inertia::render('Manage/QrResult', [
                'found' => null,
                'problem' => 'That code was not recognised. Look the appointment up by reference instead.',
            ]);
        }

        return Inertia::render('Manage/QrResult', [
            'found' => $this->summarise($appointment, $request),
            'problem' => $this->qr->isUsable($appointment)
                ? null
                : $this->qr->unusableReason($appointment),
        ]);
    }

    /**
     * Check someone in. Deliberately a plain status transition, so the QR path
     * and the typed-reference path end in exactly the same place.
     */
    public function checkIn(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('transition', [$appointment, AppointmentStatus::CheckedIn]);

        try {
            $this->statuses->transition(
                $appointment,
                AppointmentStatus::CheckedIn,
                $request->user(),
            );
        } catch (AppointmentTransitionException $exception) {
            throw $exception->toValidationException();
        }

        return back()->with('success', "{$appointment->customer->name} is checked in.");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lookupByReference(Request $request): ?array
    {
        $reference = trim($request->string('reference')->toString());

        if ($reference === '') {
            return null;
        }

        $appointment = Appointment::query()
            ->visibleTo($request->user())
            ->with(['customer.customerProfile', 'staff.user:id,name', 'items'])
            ->where('reference', $reference)
            ->first();

        return $appointment ? $this->summarise($appointment, $request) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(Appointment $appointment, Request $request): array
    {
        $timezone = config('salon.timezone');
        $appointment->loadMissing('customer.customerProfile');

        return [
            'reference' => $appointment->reference,
            'status' => $appointment->status,
            'status_label' => $appointment->status->label(),
            'time' => $appointment->starts_at->setTimezone($timezone)->format('g:i A'),
            'date' => $appointment->starts_at->setTimezone($timezone)->format('D, j M Y'),
            'customer_name' => $appointment->customer->name,
            'staff_name' => $appointment->staff->user->name,
            'services' => $appointment->items->pluck('service_name')->all(),

            // Worth knowing before someone sits down.
            'allergies' => $appointment->customer->customerProfile?->allergies,

            'can_check_in' => $request->user()->can('transition', [$appointment, AppointmentStatus::CheckedIn]),
        ];
    }
}
