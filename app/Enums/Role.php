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

    public static function commissionMarketingRoles(): array
    {
        return [self::MARKETING_LEAD, self::MARKETING];
    }

    public static function commissionMarketingValues(): array
    {
        return array_map(fn (self $role) => $role->value, self::commissionMarketingRoles());
    }

    public static function posMarketingPickerValues(): array
    {
        return [self::MARKETING_LEAD->value, self::MARKETING->value, self::MARKETING_TETAP->value];
    }

    public function isCommissionMarketing(): bool
    {
        return in_array($this, self::commissionMarketingRoles(), true);
    }
}