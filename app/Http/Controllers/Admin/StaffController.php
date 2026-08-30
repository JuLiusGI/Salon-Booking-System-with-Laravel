<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Media\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(
        private readonly ImageStorage $images,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Staff::class);

        $staff = Staff::query()
            ->with('user:id,name,email,role,avatar_path')
            ->withCount('services')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), function ($query, string $status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('display_order')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->user->role,
                'title' => $member->title,
                'is_active' => $member->is_active,
                'is_bookable' => $member->is_bookable,
                'display_order' => $member->display_order,
                'services_count' => $member->services_count,
                'photo_url' => $member->photoUrl(),
            ]);

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $staff,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Staff::class);

        return Inertia::render('Admin/Staff/Form', [
            'member' => null,
            'services' => $this->serviceOptions(),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $this->authorize('create', Staff::class);

        $staff = DB::transaction(function () use ($request) {
            // A staff member is a login plus a salon profile, so both records are
            // created together or neither is.
            $user = new User($request->safe()->only('name', 'email', 'phone'));
            $user->role = $request->role();
            $user->password = $request->string('password')->toString();
            $user->email_verified_at = now();

            if ($request->hasFile('photo')) {
                $user->avatar_path = $this->images->store($request->file('photo'), 'staff');
            }

            $user->save();

            $staff = new Staff($request->safe()->only(
                'title', 'bio', 'hired_on', 'is_active', 'is_bookable', 'display_order',
            ));
            $staff->user_id = $user->id;
            $staff->save();

            $staff->services()->sync($request->serviceIds());

            return $staff;
        });

        $this->audit->record('staff.created', $staff, [
            'name' => $staff->user->name,
            'role' => $staff->user->role->value,
        ]);

        return redirect()
            ->route('admin.staff.index')
            ->with('success', "{$staff->user->name} added to the team.");
    }

    public function edit(Staff $staff): Response
    {
        $this->authorize('update', $staff);

        $staff->load('user:id,name,email,phone,role,avatar_path');

        return Inertia::render('Admin/Staff/Form', [
            'member' => [
                'id' => $staff->id,
                'name' => $staff->user->name,
                'email' => $staff->user->email,
                'phone' => $staff->user->phone,
                'role' => $staff->user->role,
                'title' => $staff->title,
                'bio' => $staff->bio,
                'hired_on' => $staff->hired_on?->toDateString(),
                'is_active' => $staff->is_active,
                'is_bookable' => $staff->is_bookable,
                'display_order' => $staff->display_order,
                'photo_url' => $staff->photoUrl(),
                'service_ids' => $staff->services()->pluck('services.id'),
            ],
            'services' => $this->serviceOptions(),
        ]);
    }

    public function update(StaffRequest $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        DB::transaction(function () use ($request, $staff) {
            $user = $staff->user;
            $user->fill($request->safe()->only('name', 'email', 'phone'));
            $user->role = $request->role();

            if ($request->filled('password')) {
                $user->password = $request->string('password')->toString();
            }

            if ($request->hasFile('photo')) {
                $user->avatar_path = $this->images->replace(
                    $user->avatar_path,
                    $request->file('photo'),
                    'staff',
                );
            } elseif ($request->boolean('remove_photo')) {
                $this->images->delete($user->avatar_path);
                $user->avatar_path = null;
            }

            $user->save();

            $staff->fill($request->safe()->only(
                'title', 'bio', 'hired_on', 'is_active', 'is_bookable', 'display_order',
            ));
            $staff->save();

            $staff->services()->sync($request->serviceIds());
        });

        $this->audit->record('staff.updated', $staff, ['name' => $staff->user->name]);

        return redirect()
            ->route('admin.staff.index')
            ->with('success', "{$staff->user->name} updated.");
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        $name = $staff->user->name;

        DB::transaction(function () use ($staff) {
            // Soft delete so appointments keep their foreign key, and disable the
            // login so a departed employee cannot sign in afterwards.
            $staff->is_active = false;
            $staff->is_bookable = false;
            $staff->save();
            $staff->delete();

            $staff->user->is_active = false;
            $staff->user->save();
        });

        $this->audit->record('staff.deleted', $staff, ['name' => $name]);

        return redirect()
            ->route('admin.staff.index')
            ->with('success', "{$name} removed from the team and their access revoked.");
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    private function serviceOptions(): Collection
    {
        return Service::query()
            ->with('category:id,name')
            ->ordered()
            ->get()
            ->map(fn (Service $service) => [
                'value' => $service->id,
                'label' => $service->category
                    ? $service->category->name.' — '.$service->name
                    : $service->name,
            ]);
    }
}
