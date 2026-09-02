<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The endpoints a stranger can reach without signing in.
 *
 * Each one either creates a row, sends mail, or verifies a credential, so each
 * is worth scripting against and each needs a ceiling. The cache store is the
 * array driver under test, so the limiter starts empty for every test.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function registration(int $n): array
    {
        return [
            'name' => "Person {$n}",
            'email' => "person{$n}@example.test",
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ];
    }

    public function test_registration_is_capped(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->post('/register', $this->registration($i))->assertRedirect();
            $this->post('/logout');
        }

        // The eleventh in the same hour is refused by the limiter, not by
        // validation: the payload is perfectly valid.
        $this->post('/register', $this->registration(11))->assertStatus(429);

        $this->assertDatabaseMissing('users', ['email' => 'person11@example.test']);
    }

    public function test_an_ordinary_registration_is_not_impeded(): void
    {
        $this->post('/register', $this->registration(1))->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'person1@example.test']);
    }

    public function test_repeated_failed_logins_are_locked_out(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_password_reset_request_is_capped(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => $user->email]);
        }

        $this->post('/forgot-password', ['email' => $user->email])->assertStatus(429);
    }

    public function test_the_qr_endpoint_is_capped(): void
    {
        $staff = User::factory()->receptionist()->create();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($staff)->get('/qr/'.str_repeat('a', 40));
        }

        $this->actingAs($staff)->get('/qr/'.str_repeat('a', 40))->assertStatus(429);
    }
}
