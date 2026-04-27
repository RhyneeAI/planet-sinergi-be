<?php

namespace App\Enums;

enum MutationType: string
{
    case IN = 'IN';
    case OUT = 'OUT';
    case OPNAME = 'OPNAME';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match($this) {
            self::IN => 'Stok Masuk',
            self::OUT => 'Stok Keluar',
            self::OPNAME => 'Stock Opname',
        };
    }

    public function isIn(): bool
    {
        return $this === self::IN;
    }

    public function isOut(): bool
    {
        return $this === self::OUT;
    }

    public function isOpname(): bool
    {
        return $this === self::OPNAME;
    }
}