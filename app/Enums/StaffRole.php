<?php

namespace App\Enums;

enum StaffRole: string
{
    case Moderator = 'moderator';
    case SuperAdmin = 'super_admin';

    /**
     * Platform standing, mirroring MembershipRole::rank(): a staff member
     * can only act on someone they outrank, so no one can act on a peer
     * -- or on themselves.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 2,
            self::Moderator => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Moderator => 'Moderator',
            self::SuperAdmin => 'Super Admin',
        };
    }
}
