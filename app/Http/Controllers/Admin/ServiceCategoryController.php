<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceCategoryRequest;
use App\Models\ServiceCategory;
use App\Services\Audit\AuditLogger;
use App\Services\Media\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCategoryController extends Controller
{
    public function __construct(
        private readonly ImageStorage $images,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ServiceCategory::class);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => ServiceCategory::query()
                ->ordered()
                ->withCount('services')
                ->get()
                ->map(fn (ServiceCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'is_active' => $category->is_active,
                    'display_order' => $category->display_order,
                    'services_count' => $category->services_count,
                    'image_url' => $category->imageUrl(),
                ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ServiceCategory::class);

        return Inertia::render('Admin/Categories/Form', [
            'category' => null,
        ]);
    }

    public function store(ServiceCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ServiceCategory::class);

        $category = new ServiceCategory($request->safe()->only(
            'name', 'description', 'is_active', 'display_order',
        ));

        $category->slug = ServiceCategory::uniqueSlug($request->string('name')->toString());

        if ($request->hasFile('image')) {
            $category->image_path = $this->images->store($request->file('image'), 'categories');
        }

        $category->save();

        $this->audit->record('service_category.created', $category, ['name' => $category->name]);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Category \"{$category->name}\" created.");
    }

    public function edit(ServiceCategory $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('Admin/Categories/Form', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'display_order' => $category->display_order,
                'image_url' => $category->imageUrl(),
            ],
        ]);
    }

    public function update(ServiceCategoryRequest $request, ServiceCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->fill($request->safe()->only('name', 'description', 'is_active', 'display_order'));

        if ($category->isDirty('name')) {
            $category->slug = ServiceCategory::uniqueSlug($category->name, $category->id);
        }

        if ($request->hasFile('image')) {
            $category->image_path = $this->images->replace(
                $category->image_path,
                $request->file('image'),
                'categories',
            );
        } elseif ($request->boolean('remove_image')) {
            $this->images->delete($category->image_path);
            $category->image_path = null;
        }

        $category->save();

        $this->audit->record('service_category.updated', $category, ['name' => $category->name]);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Category \"{$category->name}\" updated.");
    }

    public function destroy(ServiceCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        // Services would be orphaned from their grouping, and the public site
        // reads categories to build the menu. Ask the admin to move them first
        // rather than silently hiding services.
        if ($category->services()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Move or delete this category\'s services before deleting it.',
            ]);
        }

        $name = $category->name;

        $this->images->delete($category->image_path);
        $category->image_path = null;
        $category->save();

        $category->delete();

        $this->audit->record('service_category.deleted', $category, ['name' => $name]);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Category \"{$name}\" deleted.");
    }
}
