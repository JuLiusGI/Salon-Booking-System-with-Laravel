<?php

namespace App\Http\Controllers\Manage;

use App\Enums\AppointmentStatus;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer records: who they are, what they have had done, and what the salon
 * needs to remember about them.
 *
 * Read access is deliberately wider than write access. A stylist can open a
 * customer they are treating, because an allergy matters with someone in the
 * chair, but the record itself is the front desk's to maintain.
 */
class CustomerController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewCustomers', User::class);

        $customers = User::query()
            ->where('role', UserRole::Customer)
            ->with('customerProfile:id,user_id,allergies')
            ->withCount([
                'appointments as visits_count' => fn ($q) => $q->where('status', AppointmentStatus::Completed),
                'appointments as upcoming_count' => fn ($q) => $q->where('starts_at', '>=', now())
                    ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow]),
            ])
            ->when($request->string('search')->toString(), function ($query, string $term) {
                $query->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%');
                });
            })
            ->when($request->string('status')->toString(), fn ($q, string $s) => $q->where('is_active', $s === 'active'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'is_active' => $customer->is_active,
                'visits_count' => $customer->visits_count,
                'upcoming_count' => $customer->upcoming_count,

                // Surfaced in the list so the desk can see it before opening the
                // record, without exposing the detail itself.
                'has_allergies' => filled($customer->customerProfile?->allergies),
            ]);

        return Inertia::render('Manage/Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function show(Request $request, User $customer): Response
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $this->authorize('viewCustomer', $customer);

        $customer->load('customerProfile');

        $timezone = config('salon.timezone');
        $actor = $request->user();

        $appointments = $customer->appointments()
            ->with(['staff.user:id,name', 'items'])
            // A stylist sees the visits they worked on, not the whole history.
            ->visibleTo($actor)
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get();

        return Inertia::render('Manage/Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'is_active' => $customer->is_active,
                'joined_on' => $customer->created_at->setTimezone($timezone)->format('j M Y'),
                'birthday' => $customer->customerProfile?->birthday?->toDateString(),
                'gender' => $customer->customerProfile?->gender,
                'address' => $customer->customerProfile?->address,
                'allergies' => $customer->customerProfile?->allergies,
                'preferences' => $customer->customerProfile?->preferences,
                'service_notes' => $customer->customerProfile?->service_notes,

                // Free-text staff notes are the desk's, not the stylist's.
                'notes' => $actor->can('manageCustomerRecord', User::class)
                    ? $customer->customerProfile?->notes
                    : null,
            ],

            'history' => $appointments->map(fn (Appointment $appointment) => [
                'reference' => $appointment->reference,
                'status' => $appointment->status,
                'status_label' => $appointment->status->label(),
                'date' => $appointment->starts_at->setTimezone($timezone)->format('D, j M Y'),
                'time' => $appointment->starts_at->setTimezone($timezone)->format('g:i A'),
                'staff_name' => $appointment->staff->user->name,
                'services' => $appointment->items->pluck('service_name')->all(),
                'total_price' => $appointment->total_price,
                'is_upcoming' => $appointment->ends_at->isFuture(),
            ])->values(),

            'stats' => $this->statsFor($customer),

            'can' => [
                'manage' => $actor->can('manageCustomerRecord', User::class),
            ],

            'genders' => collect(Gender::cases())->map(fn (Gender $gender) => [
                'value' => $gender->value,
                'label' => $gender->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manageCustomerRecord', User::class);

        return Inertia::render('Manage/Customers/Create');
    }

    /**
     * Create a customer at the desk, for someone who walked in without an
     * account. They can claim it later through the password reset flow.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageCustomerRecord', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $customer = DB::transaction(function () use ($validated) {
            $customer = new User($validated);
            $customer->role = UserRole::Customer;

            // A random password nobody knows. The customer sets their own
            // through the reset flow, so the desk never handles it.
            $customer->password = Hash::make(Str::random(40));
            $customer->save();

            $customer->customerProfile()->create([]);

            return $customer;
        });

        $this->audit->record('customer.created', $customer, ['name' => $customer->name]);

        return redirect()
            ->route('manage.customers.show', $customer)
            ->with('success', "{$customer->name} added. Ask them to use \"forgot password\" to set their own.");
    }

    /**
     * Update the salon's record of a customer.
     */
    public function update(Request $request, User $customer): RedirectResponse
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $this->authorize('manageCustomerRecord', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'birthday' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'preferences' => ['nullable', 'string', 'max:2000'],
            'service_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($customer, $validated) {
            $customer->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
            ])->save();

            $customer->customerProfile()->updateOrCreate([], [
                'birthday' => $validated['birthday'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'address' => $validated['address'] ?? null,
                'allergies' => $validated['allergies'] ?? null,
                'preferences' => $validated['preferences'] ?? null,
                'service_notes' => $validated['service_notes'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        // Customer records hold health information, so every edit is traceable.
        // The values themselves are never written to the log.
        $this->audit->record('customer.updated', $customer, ['name' => $customer->name]);

        return back()->with('success', 'Customer record updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function statsFor(User $customer): array
    {
        $rows = $customer->appointments()
            ->selectRaw('status, COUNT(*) as total, SUM(total_price) as value')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $completed = $rows->get(AppointmentStatus::Completed->value);
        $lastVisit = $customer->appointments()
            ->where('status', AppointmentStatus::Completed)
            ->orderByDesc('starts_at')
            ->first();

        return [
            'visits' => (int) ($completed->total ?? 0),

            // Value of completed work only. Nothing here is a payment record;
            // the system takes no money (MASTER_SPEC section 14).
            'completed_value' => number_format((float) ($completed->value ?? 0), 2, '.', ''),

            'cancelled' => (int) ($rows->get(AppointmentStatus::Cancelled->value)->total ?? 0),
            'no_shows' => (int) ($rows->get(AppointmentStatus::NoShow->value)->total ?? 0),
            'last_visit' => $lastVisit
                ? CarbonImmutable::parse($lastVisit->starts_at)
                    ->setTimezone(config('salon.timezone'))->format('j M Y')
                : null,
        ];
    }
}
