<?php

namespace App\Enums;

enum AppointmentSource: string
{
    case Online = 'online';
    case Phone = 'phone';
    case WalkIn = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Phone => 'Phone',
            self::WalkIn => 'Walk-in',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
