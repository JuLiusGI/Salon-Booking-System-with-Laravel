<?php

namespace App\Policies;

use App\Enums\UserRole;
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

    /* Customer records ------------------------------------------------------ */

    /**
     * Browsing the customer directory.
     *
     * The desk runs it. A stylist reaches a customer through an appointment they
     * are working on, not by browsing everyone the salon has ever served.
     */
    public function viewCustomers(User $actor): bool
    {
        return $this->runsTheDiary($actor);
    }

    /**
     * Opening one customer's record.
     *
     * A stylist may see a customer they are actually treating, because
     * allergies and preferences matter with someone in the chair. They may not
     * see a customer they have never had an appointment with.
     */
    public function viewCustomer(User $actor, User $customer): bool
    {
        if ($this->runsTheDiary($actor)) {
            return true;
        }

        if ($actor->hasRole(UserRole::Stylist) && $actor->staff !== null) {
            return $customer->appointments()
                ->where('staff_id', $actor->staff->getKey())
                ->exists();
        }

        return false;
    }

    /**
     * Editing the salon's notes about a customer.
     *
     * Read access is wider than write access on purpose: a stylist needs to know
     * about an allergy, but the record itself is the desk's to maintain.
     */
    public function manageCustomerRecord(User $actor): bool
    {
        return $this->runsTheDiary($actor);
    }

    private function runsTheDiary(User $actor): bool
    {
        return $actor->isAdmin() || $actor->hasRole(UserRole::Receptionist);
    }
}
