<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case NeedsReview = 'needs_review';
    case Merged = 'merged';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::NeedsReview => 'Perlu dirapikan',
            self::Merged => 'Digabung',
            self::Inactive => 'Nonaktif',
        };
    }
}
