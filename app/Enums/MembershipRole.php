<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Member => 'Member',
        };
    }
}
