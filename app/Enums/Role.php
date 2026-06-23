<?php

namespace App\Enums;

enum Role: string
{
    case SUPERADMIN = 'SUPERADMIN';
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case HRD = 'HRD';
    case MANAJER_GUDANG = 'MANAJER_GUDANG';
    case MARKETING_LEAD = 'MARKETING_LEAD';
    case MARKETING = 'MARKETING';
    case MARKETING_TETAP = 'MARKETING_TETAP';
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
            self::HRD => 'HRD',
            self::MANAJER_GUDANG => 'MANAJER_GUDANG',
            self::MARKETING_LEAD => 'MARKETING_LEAD',
            self::MARKETING => 'MARKETING',
            self::MARKETING_TETAP => 'MARKETING_TETAP',
            self::KASIR => 'KASIR',
            self::MANDOR => 'MANDOR',
            self::KARYAWAN => 'KARYAWAN',
        };
    }
}