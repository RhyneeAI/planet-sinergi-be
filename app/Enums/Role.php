<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case OWNER = 'owner';
    case MARKETING = 'marketing';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::OWNER => 'Owner',
            self::MARKETING => 'Marketing',
        };
    }
}