<?php

namespace App\Enums;

enum OpsPaymentMethod: string
{
    case TRANSFER = 'TRANSFER';
    case CASH = 'CASH';
    case SPLIT_BILL = 'SPLIT_BILL';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
