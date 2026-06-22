<?php

namespace App\Enums;

enum PosInstallmentStatus: string
{
    case ACTIVE    = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
    case OVERDUE   = 'OVERDUE';

    public static function values(): array
    {
        return [
            self::ACTIVE->value,
            self::COMPLETED->value,
            self::OVERDUE->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::ACTIVE    => 'Aktif',
            self::COMPLETED => 'Lunas',
            self::OVERDUE   => 'Jatuh Tempo',
        };
    }

    public function isActive(): bool    { return $this === self::ACTIVE; }
    public function isCompleted(): bool { return $this === self::COMPLETED; }
    public function isOverdue(): bool   { return $this === self::OVERDUE; }
}
