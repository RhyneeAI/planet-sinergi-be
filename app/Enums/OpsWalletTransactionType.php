<?php

namespace App\Enums;

enum OpsWalletTransactionType: string
{
    case CASH = 'CASH';
    case TRANSFER = 'TRANSFER';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'CASH',
            self::TRANSFER => 'TRANSFER',
        };
    }
}
