<?php

namespace Tests\Unit\Enums;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_customers_are_not_staff_members(): void
    {
        $this->assertFalse(UserRole::Customer->isStaffMember());
    }

    public function test_salon_roles_are_staff_members(): void
    {
        $this->assertTrue(UserRole::Admin->isStaffMember());
        $this->assertTrue(UserRole::Receptionist->isStaffMember());
        $this->assertTrue(UserRole::Stylist->isStaffMember());
    }

    public function test_only_stylists_are_bookable(): void
    {
        $this->assertTrue(UserRole::Stylist->isBookable());

        $this->assertFalse(UserRole::Admin->isBookable());
        $this->assertFalse(UserRole::Receptionist->isBookable());
        $this->assertFalse(UserRole::Customer->isBookable());
    }

    public function test_values_are_stable_database_strings(): void
    {
        $this->assertSame(['admin', 'receptionist', 'stylist', 'customer'], UserRole::values());
    }
}
