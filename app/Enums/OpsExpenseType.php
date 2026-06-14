<?php

namespace App\Enums;

enum OpsExpenseType: string
{
    case INTERNAL = 'INTERNAL';
    case MANDOR = 'MANDOR';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::INTERNAL => 'INTERNAL',
            self::MANDOR => 'MANDOR',
        };
    }

    public function isMandor(): bool
    {
        return $this === self::MANDOR;
    }
}
