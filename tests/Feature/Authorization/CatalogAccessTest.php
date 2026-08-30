<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Catalogue and team management is administrative. These tests exist so that a
 * route added to the wrong group is caught immediately rather than quietly
 * exposing the catalogue to every signed-in customer.
 */
class CatalogAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function adminPages(): array
    {
        return [
            'category list' => ['/admin/categories'],
            'category create' => ['/admin/categories/create'],
            'service list' => ['/admin/services'],
            'service create' => ['/admin/services/create'],
            'staff list' => ['/admin/staff'],
            'staff create' => ['/admin/staff/create'],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_only_an_admin_can_open_a_management_page(string $uri): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();

            $response = $this->actingAs($user)->get($uri);

            if ($role === UserRole::Admin) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }

    #[DataProvider('adminPages')]
    public function test_a_guest_is_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    public function test_a_non_admin_cannot_create_or_change_a_category(): void
    {
        $category = ServiceCategory::factory()->create(['name' => 'Untouched']);

        foreach ([UserRole::Customer, UserRole::Receptionist, UserRole::Stylist] as $role) {
            $actor = User::factory()->role($role)->create();

            $this->actingAs($actor)
                ->post('/admin/categories', ['name' => 'Sneaky', 'is_active' => true, 'display_order' => 0])
                ->assertForbidden();

            $this->actingAs($actor)
                ->patch("/admin/categories/{$category->id}", [
                    'name' => 'Renamed', 'is_active' => true, 'display_order' => 0,
                ])
                ->assertForbidden();

            $this->actingAs($actor)
                ->delete("/admin/categories/{$category->id}")
                ->assertForbidden();
        }

        $this->assertSame('Untouched', $category->fresh()->name);
        $this->assertDatabaseMissing('service_categories', ['name' => 'Sneaky']);
    }

    public function test_a_non_admin_cannot_create_or_change_a_service(): void
    {
        $service = Service::factory()->create(['name' => 'Untouched', 'price' => 500]);

        foreach ([UserRole::Customer, UserRole::Receptionist, UserRole::Stylist] as $role) {
            $actor = User::factory()->role($role)->create();

            $this->actingAs($actor)
                ->patch("/admin/services/{$service->id}", [
                    'service_category_id' => $service->service_category_id,
                    'name' => 'Renamed',
                    'duration_minutes' => 30,
                    'price' => '1.00',
                    'is_active' => true,
                    'display_order' => 0,
                ])
                ->assertForbidden();

            $this->actingAs($actor)->delete("/admin/services/{$service->id}")->assertForbidden();
        }

        $service->refresh();

        $this->assertSame('Untouched', $service->name);
        $this->assertSame('500.00', $service->price);
        $this->assertNotSoftDeleted($service);
    }

    public function test_a_stylist_cannot_edit_their_own_staff_record_through_the_admin_area(): void
    {
        $staff = Staff::factory()->create();

        // Self-service profile editing lives on /profile, which does not let a
        // stylist change their own bookable state or service assignments.
        $this->actingAs($staff->user)
            ->get("/admin/staff/{$staff->id}/edit")
            ->assertForbidden();

        $this->actingAs($staff->user)
            ->patch("/admin/staff/{$staff->id}", [
                'name' => $staff->user->name,
                'email' => $staff->user->email,
                'role' => UserRole::Stylist->value,
                'is_active' => true,
                'is_bookable' => true,
                'display_order' => 0,
            ])
            ->assertForbidden();
    }

    public function test_a_non_admin_cannot_remove_a_team_member(): void
    {
        $staff = Staff::factory()->create();
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)
            ->delete("/admin/staff/{$staff->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted($staff);
    }
}
