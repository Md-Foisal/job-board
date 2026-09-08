<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Closed => 'Closed',
        };
    }
}
