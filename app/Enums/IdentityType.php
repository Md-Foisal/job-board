<?php

namespace App\Enums;

enum IdentityType: string
{
    case Individual = 'individual';
    case Company = 'company';
    case Agency = 'agency';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Company => 'Company',
            self::Agency => 'Agency',
        };
    }
}
