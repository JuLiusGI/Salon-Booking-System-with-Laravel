<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Only admins may browse the staff and customer directory.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $subject): bool
    {
        return $actor->isAdmin() || $actor->is($subject);
    }

    public function update(User $actor, User $subject): bool
    {
        return $actor->isAdmin() || $actor->is($subject);
    }

    /**
     * Role changes are administrative and self-service is forbidden: an admin
     * must not be able to demote themselves and strip the salon of its last
     * administrator by accident.
     */
    public function updateRole(User $actor, User $subject): bool
    {
        return $actor->isAdmin() && ! $actor->is($subject);
    }

    public function deactivate(User $actor, User $subject): bool
    {
        return $actor->isAdmin() && ! $actor->is($subject);
    }
}
