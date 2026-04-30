<?php

namespace App\Enums;

enum Role: string
{
    case SUPERADMIN = 'SUPERADMIN';
    case OWNER = 'OWNER';
    case MARKETING = 'MARKETING';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match($this) {
            self::SUPERADMIN => 'SUPERADMIN',
            self::OWNER => 'OWNER',
            self::MARKETING => 'MARKETING',
        };
    }
}