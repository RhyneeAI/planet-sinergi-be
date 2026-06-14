<?php

namespace App\Enums;

enum OpsNotificationType: string
{
    case INCOME_PENDING = 'INCOME_PENDING';
    case EXPENSE_INSUFFICIENT_BALANCE = 'EXPENSE_INSUFFICIENT_BALANCE';
    case EXPENSE_CREATED = 'EXPENSE_CREATED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
