<?php

namespace App\Http\Controllers\Booking;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Services\Availability\AvailabilityService;
use App\Services\Booking\BookingRuleChecker;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer booking flow.
 *
 * The page is one Inertia component that walks through services, stylist, and
 * time. Availability is fetched by partial reload rather than a separate API, so
 * slots always come from the same server-side engine that will revalidate the
 * booking, and there is no second code path to keep in step.
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingService $booking,
    ) {}

    public function create(Request $request): Response
    {
        $this->authorize('create', Appointment::class);

        $rules = new BookingRuleChecker;

        $serviceIds = $this->requestedServiceIds($request);
        $services = $this->resolveServices($serviceIds);
        $staff = $this->resolveStaff($request->integer('staff_id'));

        return Inertia::render('Booking/Create', [
            'categories' => $this->catalogue(),
            'stylists' => $this->stylistsFor($services),

            'selection' => [
                'service_ids' => $services->pluck('id')->all(),
                'staff_id' => $staff?->id,
                'date' => $this->requestedDate($request)?->toDateString(),
            ],

            'summary' => [
                'duration_minutes' => $this->availability->totalDuration($services),
                'total_price' => $this->availability->totalPrice($services),
            ],

            // Reloaded on its own as the customer changes stylist or date.
            'slots' => Inertia::optional(fn () => $this->slotsFor($request, $services, $staff, $rules)),

            'booking_window' => [
                'earliest_date' => $rules->earliestStart()
                    ->setTimezone(config('salon.timezone'))->toDateString(),
                'latest_date' => $rules->latestStart()
                    ->setTimezone(config('salon.timezone'))->toDateString(),
                'min_advance_minutes' => $rules->rules()->min_advance_minutes,
            ],

            'timezone' => config('salon.timezone'),
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $this->authorize('create', Appointment::class);

        $services = $this->resolveServices($request->serviceIds());
        $staff = Staff::query()->whereKey($request->integer('staff_id'))->first();

        if ($staff === null) {
            throw BookingException::staffUnavailable()->toValidationException();
        }

        try {
            $appointment = $this->booking->book(
                customer: $request->user(),
                staff: $staff,
                services: $services,
                startsAt: $request->startsAt(),
                notes: $request->input('notes'),
            );
        } catch (BookingException $exception) {
            // A refused booking is an expected outcome, so it is reported on the
            // form rather than as a server error.
            throw $exception->toValidationException();
        }

        return redirect()
            ->route('appointments.show', $appointment->reference)
            ->with('success', 'Your appointment is booked. We have sent the details to your account.');
    }

    /**
     * @param  Collection<int, Service>  $services
     * @return array<string, mixed>
     */
    private function slotsFor(
        Request $request,
        Collection $services,
        ?Staff $staff,
        BookingRuleChecker $rules,
    ): array {
        $date = $this->requestedDate($request);

        if ($services->isEmpty() || $staff === null || $date === null) {
            return ['date' => $date?->toDateString(), 'times' => []];
        }

        return [
            'date' => $date->toDateString(),
            'times' => $this->availability
                ->slotsFor($staff, $services, $date, $rules)
                ->map(fn ($slot) => $slot->toArray())
                ->all(),
        ];
    }

    /**
     * @return list<int>
     */
    private function requestedServiceIds(Request $request): array
    {
        return array_values(array_unique(array_map(
            'intval',
            (array) $request->input('service_ids', []),
        )));
    }

    /**
     * Only ever the active, bookable catalogue. A stale id from an old tab
     * simply drops out rather than reaching the booking service.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Service>
     */
    private function resolveServices(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $services = Service::query()->active()->whereKey($ids)->get();

        // Preserve the order the customer chose them in.
        return collect($ids)
            ->map(fn (int $id) => $services->firstWhere('id', $id))
            ->filter()
            ->values();
    }

    private function resolveStaff(?int $id): ?Staff
    {
        if (! $id) {
            return null;
        }

        return Staff::query()->bookable()->whereKey($id)->first();
    }

    private function requestedDate(Request $request): ?CarbonImmutable
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
     * The bookable catalogue, grouped for the picker.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function catalogue(): Collection
    {
        return ServiceCategory::query()
            ->active()
            ->ordered()
            ->with(['services' => fn ($query) => $query->active()->ordered()])
            ->get()
            ->map(fn (ServiceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'services' => $category->services->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->price,
                ])->values(),
            ])
            ->filter(fn (array $category) => $category['services']->isNotEmpty())
            ->values();
    }

    /**
     * Stylists who can perform every chosen service.
     *
     * Filtering here is what stops a customer being offered an impossible
     * pairing; the booking service checks it again regardless.
     *
     * @param  Collection<int, Service>  $services
     * @return Collection<int, array<string, mixed>>
     */
    private function stylistsFor(Collection $services): Collection
    {
        $query = Staff::query()->bookable()->with('user:id,name,avatar_path');

        foreach ($services as $service) {
            $query->whereHas('services', fn ($q) => $q->whereKey($service->getKey()));
        }

        return $query
            ->orderBy('display_order')
            ->get()
            ->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->user->name,
                'title' => $member->title,
                'photo_url' => $member->photoUrl(),
            ]);
    }
}
