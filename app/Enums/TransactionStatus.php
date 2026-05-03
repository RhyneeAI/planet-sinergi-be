<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case UNPAID = 'UNPAID';
    case PROCESS = 'PROCESS';
    case PAID = 'PAID';
    case CANCEL = 'CANCEL';
    case PENDING = 'PENDING';

    public static function values(): array
    {
        return [
            self::UNPAID->value,
            self::PROCESS->value,
            self::PAID->value,
            self::CANCEL->value,
            self::PENDING->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::UNPAID => 'Belum Dibayar',
            self::PROCESS => 'Proses',
            self::PAID => 'Dibayar',
            self::CANCEL => 'DIbatalkan',
            self::PENDING => 'Menunggu Pembayaran',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isUnpaid(): bool
    {
        return $this === self::UNPAID;
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isProcess(): bool
    {
        return $this === self::PROCESS;
    }

    public function isCancel(): bool
    {
        return $this === self::CANCEL;
    }
}
