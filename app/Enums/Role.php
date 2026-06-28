<?php

namespace App\Enums;

enum Role: string
{
    case SUPERADMIN = 'SUPERADMIN';
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case HRD = 'HRD';
    case GUDANG = 'GUDANG';
    case KEPALA_GUDANG = 'KEPALA_GUDANG';
    case MARKETING_LEAD = 'MARKETING_LEAD';
    case MARKETING = 'MARKETING';
    case MARKETING_TETAP = 'MARKETING_TETAP';
    case KASIR = 'KASIR';
    case KEPALA_MANDOR = 'KEPALA_MANDOR';
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
            self::GUDANG => 'GUDANG',
            self::KEPALA_GUDANG => 'KEPALA_GUDANG',
            self::MARKETING_LEAD => 'MARKETING_LEAD',
            self::MARKETING => 'MARKETING',
            self::MARKETING_TETAP => 'MARKETING_TETAP',
            self::KASIR => 'KASIR',
            self::KEPALA_MANDOR => 'KEPALA_MANDOR',
            self::MANDOR => 'MANDOR',
            self::KARYAWAN => 'KARYAWAN',
        };
    }
}