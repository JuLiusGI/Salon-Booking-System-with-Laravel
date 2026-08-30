<?php

namespace App\Services\Staff;

use App\Enums\UserRole;
use App\Models\Staff;
use App\Models\User;

/**
 * Keeps a user's role and their staff record consistent.
 *
 * Phase 2 could promote a customer to stylist without giving them a staff
 * record, which left them holding a salon role but invisible to scheduling and
 * booking. This closes that gap wherever a role changes.
 */
class StaffProvisioner
{
    /**
     * Bring the staff record into line with a newly assigned role.
     */
    public function syncForRole(User $user, UserRole $role): void
    {
        $staff = Staff::withTrashed()->where('user_id', $user->getKey())->first();

        if (! $role->isStaffMember()) {
            $this->standDown($staff);

            return;
        }

        if ($staff === null) {
            // user_id is intentionally not mass assignable, so it is set
            // directly rather than passed through create().
            $staff = new Staff([
                'is_active' => true,
                'is_bookable' => $role->isBookable(),
                'display_order' => 0,
            ]);

            $staff->user_id = $user->getKey();
            $staff->save();

            return;
        }

        if ($staff->trashed()) {
            $staff->restore();
        }

        $staff->is_active = true;
        $staff->is_bookable = $role->isBookable();
        $staff->save();
    }

    /**
     * Someone has left a salon role.
     *
     * The record is deactivated rather than deleted, because appointments,
     * schedules, and history still point at it. Making them unbookable is what
     * actually removes them from the booking flow.
     */
    private function standDown(?Staff $staff): void
    {
        if ($staff === null || $staff->trashed()) {
            return;
        }

        $staff->is_active = false;
        $staff->is_bookable = false;
        $staff->save();
    }
}
