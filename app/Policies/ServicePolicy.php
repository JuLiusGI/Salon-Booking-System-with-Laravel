<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, Service $service): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, Service $service): bool
    {
        return $actor->isAdmin();
    }
}
