<?php

namespace App\Enums;

enum StockMutationType: string
{
    case PURCHASE_IN = 'PURCHASE_IN';
    case SALES_OUT   = 'SALES_OUT';
    case ADJUST_IN   = 'ADJUST_IN';
    case ADJUST_OUT  = 'ADJUST_OUT';
    case OPNAME      = 'OPNAME';

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