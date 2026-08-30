<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_a_reset_link_is_sent_to_a_registered_address(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_address_gets_the_same_response_and_no_mail(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

        // Identical confirmation to the success case, so the endpoint cannot be
        // used to discover which addresses have accounts.
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_the_reset_screen_can_be_rendered_with_a_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
            return $this->get('/reset-password/'.$notification->token)->isOk();
        });
    }

    public function test_a_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));

            return true;
        });
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_remember_token_is_rotated_so_old_cookies_die(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $original = $user->remember_token;

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, $original) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ]);

            $this->assertNotSame($original, $user->fresh()->remember_token);

            return true;
        });
    }
}
