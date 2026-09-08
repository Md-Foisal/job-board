<?php

namespace App\Enums;

enum WorkplaceType: string
{
    case Remote = 'remote';
    case Onsite = 'onsite';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Remote => 'Remote',
            self::Onsite => 'Onsite',
            self::Hybrid => 'Hybrid',
        };
    }
}
