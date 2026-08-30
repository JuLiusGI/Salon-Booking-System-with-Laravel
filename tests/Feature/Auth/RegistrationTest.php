<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nadia Ocampo',
            'email' => 'nadia@example.test',
            'phone' => '09171234567',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ], $overrides);
    }

    public function test_the_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_a_new_user_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/register', $this->validPayload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_registration_always_creates_a_customer(): void
    {
        $this->post('/register', $this->validPayload());

        $user = User::where('email', 'nadia@example.test')->firstOrFail();

        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_a_customer_profile_is_created_alongside_the_account(): void
    {
        $this->post('/register', $this->validPayload());

        $user = User::where('email', 'nadia@example.test')->firstOrFail();

        $this->assertNotNull($user->customerProfile);
    }

    public function test_the_password_is_hashed_and_never_stored_in_plain_text(): void
    {
        $this->post('/register', $this->validPayload());

        $user = User::where('email', 'nadia@example.test')->firstOrFail();

        $this->assertNotSame('correct-horse-battery', $user->password);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    public function test_a_registrant_cannot_make_themselves_an_admin(): void
    {
        $this->post('/register', $this->validPayload(['role' => 'admin']));

        $user = User::where('email', 'nadia@example.test')->firstOrFail();

        $this->assertSame(UserRole::Customer, $user->role);
    }

    public function test_an_email_address_cannot_be_registered_twice(): void
    {
        User::factory()->create(['email' => 'nadia@example.test']);

        $this->post('/register', $this->validPayload())
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_password_must_be_confirmed(): void
    {
        $this->post('/register', $this->validPayload(['password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_a_short_password_is_rejected(): void
    {
        $this->post('/register', $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');
    }
}
