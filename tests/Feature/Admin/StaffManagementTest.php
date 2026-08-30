<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Paloma Herrera',
            'email' => 'paloma@salon.test',
            'phone' => '09171234567',
            'role' => UserRole::Stylist->value,
            'password' => 'a-strong-initial-password',
            'password_confirmation' => 'a-strong-initial-password',
            'title' => 'Senior Stylist',
            'bio' => 'Fifteen years behind the chair.',
            'hired_on' => '2020-03-01',
            'is_active' => true,
            'is_bookable' => true,
            'display_order' => 0,
        ], $overrides);
    }

    public function test_adding_a_team_member_creates_both_a_login_and_a_profile(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/staff', $this->payload())
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'paloma@salon.test')->firstOrFail();

        $this->assertSame(UserRole::Stylist, $user->role);
        $this->assertTrue(Hash::check('a-strong-initial-password', $user->password));
        $this->assertNotNull($user->staff);
        $this->assertSame('Senior Stylist', $user->staff->title);
    }

    public function test_a_new_team_member_can_be_assigned_services(): void
    {
        $services = Service::factory()->count(3)->create();

        $this->actingAs($this->admin())
            ->post('/admin/staff', $this->payload(['service_ids' => $services->pluck('id')->all()]))
            ->assertSessionHasNoErrors();

        $staff = User::where('email', 'paloma@salon.test')->firstOrFail()->staff;

        $this->assertCount(3, $staff->services);
    }

    public function test_a_receptionist_cannot_be_marked_bookable(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/staff', $this->payload([
                'role' => UserRole::Receptionist->value,
                'is_bookable' => true,
            ]))
            ->assertSessionHasErrors('is_bookable');

        $this->assertDatabaseMissing('users', ['email' => 'paloma@salon.test']);
    }

    public function test_a_team_member_cannot_be_given_the_admin_role_here(): void
    {
        // Promotion to admin belongs on the users screen, where the
        // last-administrator guard lives.
        $this->actingAs($this->admin())
            ->post('/admin/staff', $this->payload(['role' => UserRole::Admin->value]))
            ->assertSessionHasErrors('role');
    }

    public function test_an_email_already_in_use_is_rejected(): void
    {
        User::factory()->create(['email' => 'paloma@salon.test']);

        $this->actingAs($this->admin())
            ->post('/admin/staff', $this->payload())
            ->assertSessionHasErrors('email');
    }

    public function test_a_weak_initial_password_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/staff', $this->payload([
                'password' => 'short',
                'password_confirmation' => 'short',
            ]))
            ->assertSessionHasErrors('password');
    }

    public function test_nothing_is_created_when_the_request_fails_validation(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/staff', $this->payload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('staff', 0);
    }

    public function test_a_team_member_can_be_updated_without_changing_their_password(): void
    {
        $staff = Staff::factory()->create();
        $original = $staff->user->password;

        $this->actingAs($this->admin())
            ->patch("/admin/staff/{$staff->id}", $this->payload([
                'email' => $staff->user->email,
                'name' => 'Renamed Stylist',
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasNoErrors();

        $staff->refresh();

        $this->assertSame('Renamed Stylist', $staff->user->name);
        $this->assertSame($original, $staff->user->password);
    }

    public function test_a_staff_photo_is_stored_and_shown_on_the_public_team_page(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post('/admin/staff', $this->payload([
            'photo' => UploadedFile::fake()->image('portrait.jpg', 600, 600),
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', 'paloma@salon.test')->firstOrFail();

        $this->assertNotNull($user->avatar_path);
        $this->assertStringStartsWith('staff/', $user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        $this->get('/team')->assertInertia(
            fn (AssertableInertia $page) => $page->where('staff.0.photo_url', fn ($url) => $url !== null)
        );
    }

    public function test_an_unsafe_photo_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/staff', $this->payload([
                'photo' => UploadedFile::fake()->create('payload.php', 20, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('photo');

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_removing_a_team_member_revokes_their_login_but_keeps_history(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/admin/staff/{$staff->id}")
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($staff);
        $this->assertFalse($staff->user->fresh()->is_active);

        // The user row survives so appointments and audit entries still resolve.
        $this->assertDatabaseHas('users', ['id' => $staff->user_id]);
    }

    public function test_a_removed_team_member_can_no_longer_sign_in(): void
    {
        $staff = Staff::factory()->create();
        $email = $staff->user->email;

        $this->actingAs($this->admin())->delete("/admin/staff/{$staff->id}");

        $this->post('/logout');

        $this->post('/login', ['email' => $email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_admin_cannot_remove_their_own_staff_record(): void
    {
        $admin = $this->admin();
        $staff = Staff::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin)
            ->delete("/admin/staff/{$staff->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted($staff);
    }

    /* The Phase 2 gap this phase closes --------------------------------- */

    public function test_promoting_a_customer_to_stylist_creates_their_staff_record(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->assertNull($customer->staff);

        $this->actingAs($admin)
            ->patch("/admin/users/{$customer->id}/role", ['role' => UserRole::Stylist->value])
            ->assertSessionHasNoErrors();

        $staff = $customer->fresh()->staff;

        $this->assertNotNull($staff, 'A promoted stylist must have a staff record to be schedulable.');
        $this->assertTrue($staff->is_active);
        $this->assertTrue($staff->is_bookable);
    }

    public function test_promoting_someone_to_receptionist_creates_an_unbookable_record(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$customer->id}/role", ['role' => UserRole::Receptionist->value]);

        $staff = $customer->fresh()->staff;

        $this->assertNotNull($staff);
        $this->assertFalse($staff->is_bookable);
    }

    public function test_demoting_a_stylist_to_customer_stands_their_staff_record_down(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$staff->user_id}/role", ['role' => UserRole::Customer->value]);

        $staff->refresh();

        $this->assertFalse($staff->is_active);
        $this->assertFalse($staff->is_bookable);

        // Kept rather than deleted, because appointments still point at it.
        $this->assertNotSoftDeleted($staff);
    }

    public function test_a_stood_down_stylist_disappears_from_the_public_team_page(): void
    {
        $staff = Staff::factory()->create();

        $this->get('/team')->assertInertia(
            fn (AssertableInertia $page) => $page->has('staff', 1)
        );

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$staff->user_id}/role", ['role' => UserRole::Customer->value]);

        $this->get('/team')->assertInertia(
            fn (AssertableInertia $page) => $page->has('staff', 0)
        );
    }
}
