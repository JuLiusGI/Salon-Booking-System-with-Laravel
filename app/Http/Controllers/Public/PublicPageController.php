<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use App\Models\SalonHour;
use App\Models\ServiceCategory;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public, unauthenticated salon website.
 *
 * Every payload here is visible to anonymous visitors, so each query selects an
 * explicit column list. Staff email addresses, phone numbers, and hire dates are
 * deliberately never exposed (MASTER_SPEC section 17).
 */
class PublicPageController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Public/Home', [
            'categories' => $this->activeCategories()->take(4)->values(),
            'staff' => $this->bookableStaff()->take(3)->values(),
            'gallery' => $this->galleryImages()->take(6)->values(),
        ]);
    }

    public function services(): Response
    {
        return Inertia::render('Public/Services', [
            'categories' => $this->activeCategories(),
        ]);
    }

    public function team(): Response
    {
        return Inertia::render('Public/Team', [
            'staff' => $this->bookableStaff(),
        ]);
    }

    public function gallery(): Response
    {
        return Inertia::render('Public/Gallery', [
            'images' => $this->galleryImages(),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Public/About');
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact', [
            'hours' => SalonHour::query()
                ->orderByRaw('CASE WHEN day_of_week = 0 THEN 7 ELSE day_of_week END')
                ->get()
                ->map(fn (SalonHour $hour) => [
                    'day_of_week' => $hour->day_of_week,
                    'opens_at' => $hour->is_closed ? null : substr((string) $hour->opens_at, 0, 5),
                    'closes_at' => $hour->is_closed ? null : substr((string) $hour->closes_at, 0, 5),
                    'is_closed' => $hour->is_closed,
                ]),
        ]);
    }

    /**
     * Active categories with their active services, in display order.
     */
    private function activeCategories(): Collection
    {
        return ServiceCategory::query()
            ->active()
            ->ordered()
            ->with(['services' => fn ($query) => $query->active()->ordered()])
            ->get()
            ->map(fn (ServiceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'services' => $category->services->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->price,
                ])->values(),
            ])
            // A category with nothing bookable in it is noise on a public page.
            ->filter(fn (array $category) => $category['services']->isNotEmpty())
            ->values();
    }

    private function bookableStaff(): Collection
    {
        return Staff::query()
            ->bookable()
            ->with('user:id,name')
            ->orderBy('display_order')
            ->get()
            ->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->user->name,
                'title' => $member->title,
                'bio' => $member->bio,
            ]);
    }

    private function galleryImages(): Collection
    {
        return GalleryImage::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (GalleryImage $image) => [
                'id' => $image->id,
                'title' => $image->title,
                'alt_text' => $image->alt_text,
            ]);
    }
}
