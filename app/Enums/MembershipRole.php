<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Member = 'member';

    /**
     * Where this role sits in the chain of command. Used to stop anyone
     * acting on a membership that outranks their own -- the rule every
     * established team product enforces (a Slack workspace admin cannot
     * demote an owner; on GitHub, changing roles is an owner ability).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Manager => 2,
            self::Member => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Member => 'Member',
        };
    }
}
