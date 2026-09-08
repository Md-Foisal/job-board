<?php

namespace App\Enums;

enum StaffRole: string
{
    case Moderator = 'moderator';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Moderator => 'Moderator',
            self::SuperAdmin => 'Super Admin',
        };
    }
}
