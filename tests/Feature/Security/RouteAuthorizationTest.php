<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every protected page, against every role, in one table.
 *
 * Written after a customer turned out to be able to open the staff diary: the
 * data was correctly scoped, so no per-feature test noticed that the screen
 * itself was never theirs to reach. Spot checks miss that; a matrix does not.
 *
 * A route is listed with the roles that may open it. Every other role must be
 * refused, and the refusal must be a 403, not an empty page.
 */
class RouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = ['admin', 'receptionist', 'stylist', 'customer'];

    private const STAFF = ['admin', 'receptionist', 'stylist'];

    private const DESK = ['admin', 'receptionist'];

    private const ADMIN = ['admin'];

    /** @return array<string, array{string, list<string>}> */
    public static function routes(): array
    {
        $cases = [
            'dashboard' => ['/dashboard', self::ALL],
            'own appointments' => ['/appointments', self::ALL],
            'notifications' => ['/notifications', self::ALL],
            'profile' => ['/profile', self::ALL],

            'the diary calendar' => ['/manage/calendar', self::STAFF],
            'the appointment list' => ['/manage/appointments', self::STAFF],
            'the check-in desk' => ['/manage/check-in', self::STAFF],

            'the customer directory' => ['/manage/customers', self::DESK],
            'creating a customer' => ['/manage/customers/new', self::DESK],
            'booking for a customer' => ['/manage/appointments/new', self::DESK],
            'salon reports' => ['/manage/reports', self::DESK],

            'the user directory' => ['/admin/users', self::ADMIN],
            'categories' => ['/admin/categories', self::ADMIN],
            'services' => ['/admin/services', self::ADMIN],
            'the team' => ['/admin/staff', self::ADMIN],
            'salon hours' => ['/admin/schedule/hours', self::ADMIN],
            'schedule exceptions' => ['/admin/schedule/exceptions', self::ADMIN],
            'booking rules' => ['/admin/schedule/rules', self::ADMIN],
        ];

        return $cases;
    }

    /**
     * @param  list<string>  $allowed
     */
    #[DataProvider('routes')]
    public function test_the_route_admits_exactly_the_roles_it_should(string $url, array $allowed): void
    {
        foreach (self::ALL as $role) {
            $user = User::factory()->role(UserRole::from($role))->create();

            $response = $this->actingAs($user)->get($url);

            if (in_array($role, $allowed, true)) {
                $this->assertTrue(
                    $response->isSuccessful(),
                    "A {$role} should be able to open {$url}, got {$response->getStatusCode()}.",
                );
            } else {
                $this->assertSame(
                    403,
                    $response->getStatusCode(),
                    "A {$role} must be refused {$url}, got {$response->getStatusCode()}.",
                );
            }
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    #[DataProvider('routes')]
    public function test_the_route_turns_a_guest_away(string $url, array $allowed): void
    {
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_a_deactivated_staff_member_loses_access_immediately(): void
    {
        $user = User::factory()->receptionist()->create();

        $this->actingAs($user)->get('/manage/calendar')->assertOk();

        // Assigned rather than mass-assigned: is_active is deliberately absent
        // from the model's fillable list, so update() would quietly do nothing.
        $user->is_active = false;
        $user->save();

        $this->actingAs($user)->get('/manage/calendar')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
