<?php

namespace App\Enums;

enum Role: string
{
    case SUPERADMIN = 'SUPERADMIN';
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case MARKETING = 'MARKETING';
    case KASIR = 'KASIR';
    case MANDOR = 'MANDOR';
    case KARYAWAN = 'KARYAWAN';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'SUPERADMIN',
            self::OWNER => 'OWNER',
            self::ADMIN => 'ADMIN',
            self::MARKETING => 'MARKETING',
            self::KASIR => 'KASIR',
            self::MANDOR => 'MANDOR',
            self::KARYAWAN => 'KARYAWAN',
        };
    }
}