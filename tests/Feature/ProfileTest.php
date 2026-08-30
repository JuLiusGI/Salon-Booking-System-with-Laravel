<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_reach_the_profile_page(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_the_profile_page_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->customerProfile()->create([]);

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_a_user_can_update_their_details(): void
    {
        $user = User::factory()->create();
        $user->customerProfile()->create([]);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Renamed Person',
            'email' => 'renamed@example.test',
            'phone' => '09987654321',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Renamed Person', $user->name);
        $this->assertSame('renamed@example.test', $user->email);
    }

    public function test_changing_the_email_clears_the_verification_timestamp(): void
    {
        $user = User::factory()->create();
        $user->customerProfile()->create([]);

        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'moved@example.test',
        ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_customer_profile_fields_are_saved(): void
    {
        $user = User::factory()->create();
        $user->customerProfile()->create([]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'birthday' => '1995-04-12',
            'gender' => Gender::Female->value,
            'allergies' => 'Sensitive to ammonia',
        ])->assertSessionHasNoErrors();

        $profile = $user->fresh()->customerProfile;

        $this->assertSame('1995-04-12', $profile->birthday->toDateString());
        $this->assertSame(Gender::Female, $profile->gender);
        $this->assertSame('Sensitive to ammonia', $profile->allergies);
    }

    public function test_staff_do_not_get_a_customer_profile_record(): void
    {
        $staff = Staff::factory()->create();
        $user = $staff->user;

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'allergies' => 'should be ignored',
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->customerProfile);
    }

    public function test_a_user_cannot_take_an_email_already_in_use(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'taken@example.test',
        ])->assertSessionHasErrors('email');
    }

    public function test_a_user_can_keep_their_own_email_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Same Email',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();
    }

    public function test_the_password_can_be_changed_with_the_correct_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'a-much-better-password',
            'password_confirmation' => 'a-much-better-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-much-better-password', $user->fresh()->password));
    }

    public function test_the_password_cannot_be_changed_without_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'guessing',
            'password' => 'a-much-better-password',
            'password_confirmation' => 'a-much-better-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
