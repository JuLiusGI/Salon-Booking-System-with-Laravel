<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, Staff $staff): bool
    {
        return $actor->isAdmin();
    }

    /**
     * An admin must not be able to remove their own staff record and lock
     * themselves out of the schedule they manage.
     */
    public function delete(User $actor, Staff $staff): bool
    {
        return $actor->isAdmin() && $staff->user_id !== $actor->getKey();
    }
}
