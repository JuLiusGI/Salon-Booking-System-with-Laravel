<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes that require authentication, with the roles allowed to reach them.
     *
     * @return array<string, array{0: string, 1: list<UserRole>}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'dashboard' => ['/dashboard', [UserRole::Admin, UserRole::Receptionist, UserRole::Stylist, UserRole::Customer]],
            'profile' => ['/profile', [UserRole::Admin, UserRole::Receptionist, UserRole::Stylist, UserRole::Customer]],
            'admin user directory' => ['/admin/users', [UserRole::Admin]],
        ];
    }

    /**
     * @param  list<UserRole>  $allowed
     */
    #[DataProvider('protectedRoutes')]
    public function test_each_role_gets_the_expected_response(string $uri, array $allowed): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();

            $response = $this->actingAs($user)->get($uri);

            if (in_array($role, $allowed, true)) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }

    #[DataProvider('protectedRoutes')]
    public function test_a_guest_is_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    public function test_a_customer_cannot_reach_any_admin_endpoint(): void
    {
        $customer = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($customer)->get('/admin/users')->assertForbidden();

        $this->actingAs($customer)
            ->patch("/admin/users/{$target->id}/role", ['role' => 'admin'])
            ->assertForbidden();

        $this->actingAs($customer)
            ->patch("/admin/users/{$target->id}/status", ['is_active' => false])
            ->assertForbidden();

        // The attempt must not have changed anything.
        $this->assertSame(UserRole::Customer, $target->fresh()->role);
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_salon_staff_are_still_not_administrators(): void
    {
        foreach ([UserRole::Receptionist, UserRole::Stylist] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/admin/users')
                ->assertForbidden();
        }
    }

    public function test_a_user_deactivated_mid_session_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        // An admin deactivates the account while the session is still alive.
        // is_active is not mass assignable by design, so it is set directly.
        $user->is_active = false;
        $user->save();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
