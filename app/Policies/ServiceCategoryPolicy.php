<?php

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;

/**
 * Catalogue management is an administrative responsibility. Receptionists and
 * stylists work with the catalogue but do not change it (MASTER_SPEC section 4).
 */
class ServiceCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, ServiceCategory $category): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, ServiceCategory $category): bool
    {
        return $actor->isAdmin();
    }
}
