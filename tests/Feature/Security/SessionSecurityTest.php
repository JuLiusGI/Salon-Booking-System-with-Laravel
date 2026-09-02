<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Session handling around credentials.
 *
 * A session is bound to the password it was opened with, so changing a password
 * ends every other session. That matters because changing a password is what
 * someone does when they believe another person is already inside the account.
 */
class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'old-password-here';

    private const NEW = 'a-brand-new-password';

    private function withPassword(string $password): User
    {
        $user = User::factory()->create();
        $user->password = $password;
        $user->save();

        return $user;
    }

    public function test_changing_a_password_rebinds_the_session(): void
    {
        $user = $this->withPassword(self::OLD);

        $this->post('/login', ['email' => $user->email, 'password' => self::OLD])
            ->assertRedirect();

        // The binding is written by AuthenticateSession as a request passes
        // through it, so it is read after a page load rather than after login.
        $this->get('/dashboard')->assertOk();

        $before = session('password_hash_web');
        $this->assertNotNull($before, 'The session should be bound to the password hash.');

        $this->put('/profile/password', [
            'current_password' => self::OLD,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertSessionHasNoErrors();

        // The session this change was made from stays signed in, rebound to the
        // new hash. Every other session still carries the old one.
        $this->assertAuthenticated();
        $this->assertNotSame($before, session('password_hash_web'));
    }

    public function test_a_session_holding_a_stale_password_is_signed_out(): void
    {
        $user = $this->withPassword(self::OLD);

        $this->post('/login', ['email' => $user->email, 'password' => self::OLD]);
        $this->get('/dashboard')->assertOk();

        // Stand in for another browser that signed in before the password
        // changed: same user, session bound to the previous hash.
        session(['password_hash_web' => Hash::make('some-other-password')]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_password_change_is_recorded_without_the_password(): void
    {
        $user = $this->withPassword(self::OLD);

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => self::OLD,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password_changed',
            'user_id' => $user->id,
        ]);

        $logged = \DB::table('audit_logs')->where('action', 'user.password_changed')->value('metadata');

        $this->assertNotContains(self::OLD, (array) json_decode((string) $logged, true) ?: []);
        $this->assertStringNotContainsString(self::NEW, (string) $logged);
    }

    public function test_the_password_itself_actually_changed(): void
    {
        $user = $this->withPassword(self::OLD);

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => self::OLD,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
    }

    public function test_the_wrong_current_password_changes_nothing(): void
    {
        $user = $this->withPassword(self::OLD);

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'not-the-current-one',
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertSessionHasErrors();

        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }
}
