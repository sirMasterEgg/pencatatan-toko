<?php

namespace App\Enums;

enum Channel: string
{
    case Store = 'store';
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Store => 'Toko',
            self::Whatsapp => 'WhatsApp',
            self::Phone => 'Telepon',
        };
    }
}
