<?php

namespace Tests\Feature\Public;

use App\Models\GalleryImage;
use App\Models\SalonHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\SalonHoursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function publicPages(): array
    {
        return [
            'home' => ['/', 'Public/Home'],
            'services' => ['/services', 'Public/Services'],
            'team' => ['/team', 'Public/Team'],
            'gallery' => ['/gallery', 'Public/Gallery'],
            'about' => ['/about', 'Public/About'],
            'contact' => ['/contact', 'Public/Contact'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_a_guest_can_view_every_public_page(string $uri, string $component): void
    {
        $this->get($uri)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_remain_available_to_signed_in_users(string $uri, string $component): void
    {
        $this->actingAs(User::factory()->create())
            ->get($uri)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    public function test_the_services_page_lists_active_services_by_category(): void
    {
        $category = ServiceCategory::factory()->create(['name' => 'Hair']);
        Service::factory()->count(2)->create(['service_category_id' => $category->id]);

        $this->get('/services')->assertInertia(
            fn (Assert $page) => $page
                ->has('categories', 1)
                ->has('categories.0.services', 2)
                ->where('categories.0.name', 'Hair')
        );
    }

    public function test_inactive_services_and_categories_are_hidden_from_the_public(): void
    {
        $visible = ServiceCategory::factory()->create();
        Service::factory()->create(['service_category_id' => $visible->id]);
        Service::factory()->inactive()->create(['service_category_id' => $visible->id]);

        // A category whose only service is inactive should disappear entirely
        // rather than render as an empty heading.
        $empty = ServiceCategory::factory()->create();
        Service::factory()->inactive()->create(['service_category_id' => $empty->id]);

        ServiceCategory::factory()->inactive()->create();

        $this->get('/services')->assertInertia(
            fn (Assert $page) => $page
                ->has('categories', 1)
                ->has('categories.0.services', 1)
        );
    }

    public function test_the_team_page_only_lists_bookable_active_staff(): void
    {
        Staff::factory()->count(2)->create();
        Staff::factory()->notBookable()->create();
        Staff::factory()->inactive()->create();

        $this->get('/team')->assertInertia(fn (Assert $page) => $page->has('staff', 2));
    }

    public function test_the_team_page_never_exposes_staff_contact_details(): void
    {
        Staff::factory()->create();

        $this->get('/team')->assertInertia(
            fn (Assert $page) => $page->has(
                'staff.0',
                fn (Assert $member) => $member->hasAll(['id', 'name', 'title', 'bio'])
            )
        );
    }

    public function test_staff_email_addresses_do_not_appear_anywhere_in_the_team_response(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->get('/team');

        $response->assertDontSee($staff->user->email, escape: false);
        $response->assertDontSee(e($staff->user->email), escape: false);
    }

    public function test_the_gallery_shows_only_active_images_in_order(): void
    {
        GalleryImage::factory()->create(['title' => 'Second', 'display_order' => 2]);
        GalleryImage::factory()->create(['title' => 'First', 'display_order' => 1]);
        GalleryImage::factory()->create(['title' => 'Hidden', 'is_active' => false]);

        $this->get('/gallery')->assertInertia(
            fn (Assert $page) => $page
                ->has('images', 2)
                ->where('images.0.title', 'First')
                ->where('images.1.title', 'Second')
        );
    }

    public function test_the_contact_page_publishes_opening_hours_starting_on_monday(): void
    {
        $this->seed(SalonHoursSeeder::class);

        $this->get('/contact')->assertInertia(
            fn (Assert $page) => $page
                ->has('hours', 7)
                // Sunday is sorted last so the week reads Monday first.
                ->where('hours.0.day_of_week', 1)
                ->where('hours.6.day_of_week', 0)
                ->where('hours.6.is_closed', true)
        );
    }

    public function test_closed_days_publish_no_opening_times(): void
    {
        SalonHour::factory()->closed()->create(['day_of_week' => 0]);

        $this->get('/contact')->assertInertia(
            fn (Assert $page) => $page
                ->where('hours.0.is_closed', true)
                ->where('hours.0.opens_at', null)
                ->where('hours.0.closes_at', null)
        );
    }

    public function test_the_home_page_previews_a_limited_amount_of_content(): void
    {
        $category = ServiceCategory::factory()->create();
        Service::factory()->create(['service_category_id' => $category->id]);

        ServiceCategory::factory()->count(6)->create()->each(function (ServiceCategory $extra) {
            Service::factory()->create(['service_category_id' => $extra->id]);
        });

        Staff::factory()->count(5)->create();
        GalleryImage::factory()->count(10)->create();

        $this->get('/')->assertInertia(
            fn (Assert $page) => $page
                ->has('categories', 4)
                ->has('staff', 3)
                ->has('gallery', 6)
        );
    }

    public function test_empty_data_does_not_break_the_public_pages(): void
    {
        foreach (['/', '/services', '/team', '/gallery', '/contact'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_public_listings_do_not_issue_a_query_per_row(): void
    {
        $categories = ServiceCategory::factory()->count(5)->create();

        foreach ($categories as $category) {
            Service::factory()->count(4)->create(['service_category_id' => $category->id]);
        }

        Staff::factory()->count(6)->create();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->get('/services')->assertOk();
        $servicesQueries = $queries;

        $queries = 0;
        $this->get('/team')->assertOk();
        $teamQueries = $queries;

        // Eager loading means the count stays flat regardless of how many rows
        // exist. A per-row query would put these well into the dozens.
        $this->assertLessThanOrEqual(5, $servicesQueries, "The services page ran {$servicesQueries} queries.");
        $this->assertLessThanOrEqual(5, $teamQueries, "The team page ran {$teamQueries} queries.");
    }
}
