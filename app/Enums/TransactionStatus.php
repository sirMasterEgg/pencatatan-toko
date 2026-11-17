<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Confirmed => 'Dikonfirmasi',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
