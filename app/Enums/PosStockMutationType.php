<?php

namespace App\Enums;

enum PosStockMutationType: string
{
    case PURCHASE_IN = 'PURCHASE_IN';
    case SALES_OUT   = 'SALES_OUT';
    case ADJUST_IN   = 'ADJUST_IN';
    case ADJUST_OUT  = 'ADJUST_OUT';
    case OPNAME      = 'OPNAME';

    public static function values(): array
    {
        return [
            self::PURCHASE_IN->value,
            self::SALES_OUT->value,
            self::ADJUST_IN->value,
            self::ADJUST_OUT->value,
            self::OPNAME->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::PURCHASE_IN => 'Pembelian',
            self::SALES_OUT => 'Penjualan',
            self::ADJUST_IN => 'Penyesuaian Masuk',
            self::ADJUST_OUT => 'Penyesuaian Keluar',
            self::OPNAME => 'Stok Opname',
        };
    }

    public function isIncoming(): bool
    {
        return in_array($this, [self::PURCHASE_IN, self::ADJUST_IN]);
    }

    public function isOutgoing(): bool
    {
        return in_array($this, [self::SALES_OUT, self::ADJUST_OUT]);
    }

    public function isOpname(): bool
    {
        return $this === self::OPNAME;
    }
}
