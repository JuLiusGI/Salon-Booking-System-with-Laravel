<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Stylist = 'stylist';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Receptionist => 'Receptionist',
            self::Stylist => 'Stylist',
            self::Customer => 'Customer',
        };
    }

    /**
     * Roles that work at the salon and therefore have a staff record.
     */
    public function isStaffMember(): bool
    {
        return in_array($this, [self::Admin, self::Receptionist, self::Stylist], true);
    }

    /**
     * Roles that may be booked by a customer as a preferred stylist.
     *
     * Whether an individual staff member is actually bookable is controlled by
     * the `is_bookable` flag on their staff record; this only says which roles
     * are eligible at all.
     */
    public function isBookable(): bool
    {
        return $this === self::Stylist;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
