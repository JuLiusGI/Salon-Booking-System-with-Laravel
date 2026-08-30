<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->when($request->string('role')->toString(), function ($query, string $role) {
                $query->where('role', UserRole::from($role));
            })
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            // Only the columns the directory actually renders. Password hashes
            // and tokens must never reach the frontend.
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only('role', 'search'),
            'roles' => collect(UserRole::cases())
                ->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])
                ->all(),
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $this->authorize('updateRole', $user);

        $previous = $user->role;
        $next = $request->role();

        if ($previous === $next) {
            return back();
        }

        $this->ensureAnotherAdminRemains($user, $previous, $next);

        $user->role = $next;
        $user->save();

        app(AuditLogger::class)->record('user.role_changed', $user, [
            'from' => $previous->value,
            'to' => $next->value,
        ]);

        return back()->with('success', "{$user->name} is now a {$next->label()}.");
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $validated['is_active'] && $user->isAdmin()) {
            $this->ensureAnotherActiveAdminRemains($user);
        }

        $user->is_active = $validated['is_active'];
        $user->save();

        app(AuditLogger::class)->record(
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $user,
        );

        return back()->with('success', 'Account status updated.');
    }

    /**
     * Refuse a role change that would leave the salon with no administrator.
     */
    private function ensureAnotherAdminRemains(User $user, UserRole $previous, UserRole $next): void
    {
        if ($previous !== UserRole::Admin || $next === UserRole::Admin) {
            return;
        }

        $this->ensureAnotherActiveAdminRemains($user);
    }

    private function ensureAnotherActiveAdminRemains(User $user): void
    {
        $remaining = User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();

        if (! $remaining) {
            throw ValidationException::withMessages([
                'role' => 'The salon must keep at least one active administrator.',
            ]);
        }
    }
}
