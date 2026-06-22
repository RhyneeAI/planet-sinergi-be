<?php

namespace App\Enums;

enum PosPaymentType: string
{
    case CASH = 'CASH';
    case TRANSFER = 'TRANSFER';
    case QRIS = 'QRIS';
    case CICIL = 'CICIL';

    public static function values(): array
    {
        return [
            self::CASH->value,
            self::TRANSFER->value,
            self::QRIS->value,
            self::CICIL->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::CASH => 'CASH',
            self::TRANSFER => 'TRANSFER',
            self::QRIS => 'QRIS',
            self::CICIL => 'CICIL',
        };
    }

    public function isCash(): bool
    {
        return $this === self::CASH;
    }

    public function isTransfer(): bool
    {
        return $this === self::TRANSFER;
    }

    public function isQris(): bool
    {
        return $this === self::QRIS;
    }

    public function isCicil(): bool
    {
        return $this === self::QRIS;
    }
}
