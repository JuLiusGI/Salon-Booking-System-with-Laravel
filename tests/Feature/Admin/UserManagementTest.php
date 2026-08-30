<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_an_admin_can_see_the_user_directory(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 4)
            );
    }

    public function test_the_directory_never_exposes_password_hashes_or_tokens(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page->has(
                'users.data.0',
                fn (Assert $row) => $row->hasAll(['id', 'name', 'email', 'role', 'is_active'])
            ));
    }

    public function test_the_directory_can_be_filtered_by_role(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();
        User::factory()->stylist()->count(2)->create();

        $this->actingAs($admin)
            ->get('/admin/users?role=stylist')
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 2));
    }

    public function test_an_admin_can_change_another_users_role(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/users/{$customer->id}/role", ['role' => UserRole::Stylist->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Stylist, $customer->fresh()->role);
    }

    public function test_a_role_change_is_written_to_the_audit_log(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/users/{$customer->id}/role", ['role' => UserRole::Receptionist->value]);

        $log = AuditLog::query()->where('action', 'user.role_changed')->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($customer->id, $log->auditable_id);
        $this->assertSame('customer', $log->metadata['from']);
        $this->assertSame('receptionist', $log->metadata['to']);
    }

    public function test_an_admin_cannot_change_their_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/role", ['role' => UserRole::Customer->value])
            ->assertForbidden();

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_the_last_administrator_cannot_be_demoted(): void
    {
        $admin = $this->admin();
        $second = User::factory()->admin()->create();

        // Demoting the second admin is fine while the first still exists.
        $this->actingAs($admin)
            ->patch("/admin/users/{$second->id}/role", ['role' => UserRole::Stylist->value])
            ->assertSessionHasNoErrors();

        // Now the acting admin is the only one left, and cannot be removed
        // (self-demotion is already blocked, so a third admin does the attempt).
        $third = User::factory()->admin()->create();

        $this->actingAs($third)
            ->patch("/admin/users/{$admin->id}/role", ['role' => UserRole::Customer->value])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->patch("/admin/users/{$third->id}/role", ['role' => UserRole::Customer->value])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Admin, $third->fresh()->role);
    }

    public function test_an_invalid_role_value_is_rejected(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/users/{$customer->id}/role", ['role' => 'superuser'])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Customer, $customer->fresh()->role);
    }

    public function test_an_admin_can_deactivate_and_reactivate_an_account(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/users/{$customer->id}/status", ['is_active' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($customer->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated']);

        $this->actingAs($admin)
            ->patch("/admin/users/{$customer->id}/status", ['is_active' => true]);

        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_an_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/status", ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }
}
