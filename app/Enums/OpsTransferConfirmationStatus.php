<?php

namespace App\Enums;

enum OpsTransferConfirmationStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case REJECTED = 'REJECTED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'PENDING',
            self::CONFIRMED => 'CONFIRMED',
            self::REJECTED => 'REJECTED',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }
}
