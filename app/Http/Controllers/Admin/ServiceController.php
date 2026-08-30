<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Services\Audit\AuditLogger;
use App\Services\Media\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ImageStorage $images,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Service::class);

        $services = Service::query()
            ->with(['category:id,name'])
            ->withCount('staff')
            ->when($request->integer('category'), fn ($query, int $id) => $query->where('service_category_id', $id))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->string('status')->toString(), function ($query, string $status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('service_category_id')
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category?->name,
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
                'is_active' => $service->is_active,
                'display_order' => $service->display_order,
                'staff_count' => $service->staff_count,
                'image_url' => $service->imageUrl(),
            ]);

        return Inertia::render('Admin/Services/Index', [
            'services' => $services,
            'filters' => $request->only('search', 'category', 'status'),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Service::class);

        return Inertia::render('Admin/Services/Form', [
            'service' => null,
            'categories' => $this->categoryOptions(),
            'staff' => $this->staffOptions(),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Service::class);

        $service = DB::transaction(function () use ($request) {
            $service = new Service($request->safe()->only(
                'service_category_id', 'name', 'description',
                'duration_minutes', 'price', 'is_active', 'display_order',
            ));

            $service->slug = Service::uniqueSlug($request->string('name')->toString());

            if ($request->hasFile('image')) {
                $service->image_path = $this->images->store($request->file('image'), 'services');
            }

            $service->save();
            $service->staff()->sync($request->staffIds());

            return $service;
        });

        $this->audit->record('service.created', $service, ['name' => $service->name]);

        return redirect()
            ->route('admin.services.index')
            ->with('success', "Service \"{$service->name}\" created.");
    }

    public function edit(Service $service): Response
    {
        $this->authorize('update', $service);

        return Inertia::render('Admin/Services/Form', [
            'service' => [
                'id' => $service->id,
                'service_category_id' => $service->service_category_id,
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
                'is_active' => $service->is_active,
                'display_order' => $service->display_order,
                'image_url' => $service->imageUrl(),
                'staff_ids' => $service->staff()->pluck('staff.id'),
            ],
            'categories' => $this->categoryOptions(),
            'staff' => $this->staffOptions(),
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);

        DB::transaction(function () use ($request, $service) {
            $service->fill($request->safe()->only(
                'service_category_id', 'name', 'description',
                'duration_minutes', 'price', 'is_active', 'display_order',
            ));

            if ($service->isDirty('name')) {
                $service->slug = Service::uniqueSlug($service->name, $service->id);
            }

            if ($request->hasFile('image')) {
                $service->image_path = $this->images->replace(
                    $service->image_path,
                    $request->file('image'),
                    'services',
                );
            } elseif ($request->boolean('remove_image')) {
                $this->images->delete($service->image_path);
                $service->image_path = null;
            }

            $service->save();
            $service->staff()->sync($request->staffIds());
        });

        // Price and duration changes only affect future bookings. Existing
        // appointment items keep their snapshot, which is what makes historical
        // appointments stay accurate.
        $this->audit->record('service.updated', $service, ['name' => $service->name]);

        return redirect()
            ->route('admin.services.index')
            ->with('success', "Service \"{$service->name}\" updated.");
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->authorize('delete', $service);

        $name = $service->name;

        // Soft delete only. The row stays so past appointment items keep their
        // foreign key, and their snapshot keeps the history readable.
        $service->delete();

        $this->audit->record('service.deleted', $service, ['name' => $name]);

        return redirect()
            ->route('admin.services.index')
            ->with('success', "Service \"{$name}\" deleted.");
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    private function categoryOptions(): Collection
    {
        return ServiceCategory::query()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (ServiceCategory $category) => [
                'value' => $category->id,
                'label' => $category->name,
            ]);
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    private function staffOptions(): Collection
    {
        return Staff::query()
            ->bookable()
            ->with('user:id,name')
            ->orderBy('display_order')
            ->get()
            ->map(fn (Staff $member) => [
                'value' => $member->id,
                'label' => $member->user->name,
            ]);
    }
}
