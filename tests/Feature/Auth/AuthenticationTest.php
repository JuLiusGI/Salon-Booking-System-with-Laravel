<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_a_user_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_a_user_cannot_authenticate_with_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_session_id_is_regenerated_on_login(): void
    {
        $user = User::factory()->create();

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_repeated_failed_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        // The sixth attempt is throttled even though it uses the real password,
        // which is what stops credential stuffing.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $errors = session('errors')->get('email');
        $this->assertStringContainsString('seconds', $errors[0]);
    }

    public function test_the_rate_limiter_is_cleared_after_a_successful_login(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts(strtolower($user->email).'|127.0.0.1'));
    }

    public function test_a_user_can_log_out_and_the_session_is_invalidated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('home'));
    }

    public function test_an_authenticated_user_is_redirected_away_from_the_login_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect();
    }
}
