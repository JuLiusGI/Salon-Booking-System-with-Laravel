<?php

namespace App\Http\Controllers\Manage;

use App\Enums\AppointmentSource;
use App\Enums\UserRole;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\Booking\BookingRuleChecker;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Booking on a customer's behalf, at the desk or over the phone.
 *
 * Goes through the same BookingService as a customer booking, so the same locked
 * revalidation applies. What differs is only who is recorded as having made it,
 * and that the desk may set the channel it came through.
 */
class StaffBookingController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingService $booking,
    ) {}

    public function create(Request $request): Response
    {
        $this->authorize('createForCustomer', Appointment::class);

        $rules = new BookingRuleChecker;
        $timezone = config('salon.timezone');

        $serviceIds = array_map('intval', (array) $request->input('service_ids', []));
        $services = $this->resolveServices($serviceIds);
        $staff = $request->integer('staff_id')
            ? Staff::query()->bookable()->whereKey($request->integer('staff_id'))->first()
            : null;
        $date = $this->resolveDate($request);

        return Inertia::render('Manage/Appointments/Create', [
            'categories' => $this->catalogue(),
            'stylists' => $this->stylistsFor($services),
            'customers' => $this->customerOptions($request->string('customer_search')->toString()),

            'selection' => [
                'service_ids' => $services->pluck('id')->all(),
                'staff_id' => $staff?->id,
                'date' => $date?->toDateString(),
                'customer_search' => $request->string('customer_search')->toString(),
            ],

            'summary' => [
                'duration_minutes' => $this->availability->totalDuration($services),
                'total_price' => $this->availability->totalPrice($services),
            ],

            'slots' => Inertia::optional(function () use ($staff, $services, $date, $rules) {
                if ($staff === null || $services->isEmpty() || $date === null) {
                    return ['date' => $date?->toDateString(), 'times' => []];
                }

                return [
                    'date' => $date->toDateString(),
                    'times' => $this->availability->slotsFor($staff, $services, $date, $rules)
                        ->map(fn ($slot) => $slot->toArray())
                        ->all(),
                ];
            }),

            'sources' => collect(AppointmentSource::cases())->map(fn (AppointmentSource $source) => [
                'value' => $source->value,
                'label' => $source->label(),
            ]),

            'booking_window' => [
                'earliest_date' => $rules->earliestStart()->setTimezone($timezone)->toDateString(),
                'latest_date' => $rules->latestStart()->setTimezone($timezone)->toDateString(),
            ],

            'timezone' => $timezone,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('createForCustomer', Appointment::class);

        $validated = $request->validate([
            'customer_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Customer->value)
                    ->whereNull('deleted_at'),
            ],
            'service_ids' => ['required', 'array', 'min:1', 'max:6'],
            'service_ids.*' => [
                'integer',
                Rule::exists('services', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'staff_id' => [
                'required', 'integer',
                Rule::exists('staff', 'id')
                    ->where('is_active', true)
                    ->where('is_bookable', true)
                    ->whereNull('deleted_at'),
            ],
            'starts_at' => ['required', 'date'],
            'source' => ['required', Rule::enum(AppointmentSource::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'customer_id.exists' => 'Choose an existing customer account.',
        ]);

        $customer = User::query()->findOrFail($validated['customer_id']);
        $staff = Staff::query()->findOrFail($validated['staff_id']);
        $services = $this->resolveServices(array_map('intval', $validated['service_ids']));

        try {
            $appointment = $this->booking->book(
                customer: $customer,
                staff: $staff,
                services: $services,
                startsAt: CarbonImmutable::parse($validated['starts_at'])->utc(),
                notes: $validated['notes'] ?? null,

                // Recorded so the salon can tell who took the booking.
                bookedBy: $request->user(),
                source: AppointmentSource::from($validated['source']),
            );
        } catch (BookingException $exception) {
            throw $exception->toValidationException();
        }

        return redirect()
            ->route('manage.appointments.show', $appointment->reference)
            ->with('success', "Booked for {$customer->name}. Reference {$appointment->reference}.");
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Service>
     */
    private function resolveServices(array $ids): Collection
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return collect();
        }

        $services = Service::query()->active()->whereKey($ids)->get();

        return collect($ids)
            ->map(fn (int $id) => $services->firstWhere('id', $id))
            ->filter()
            ->values();
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
     * @return Collection<int, array<string, mixed>>
     */
    private function catalogue(): Collection
    {
        return ServiceCategory::query()
            ->active()
            ->ordered()
            ->with(['services' => fn ($q) => $q->active()->ordered()])
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
     * @param  Collection<int, Service>  $services
     * @return Collection<int, array<string, mixed>>
     */
    private function stylistsFor(Collection $services): Collection
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

    /**
     * Customers matching a search. Deliberately capped and search-driven rather
     * than dumping the whole customer list into every page load.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function customerOptions(string $search): Collection
    {
        if (trim($search) === '') {
            return collect();
        }

        return User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }
}
