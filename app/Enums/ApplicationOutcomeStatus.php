<?php

namespace App\Enums;

/**
 * The life-cycle outcome of an application -- distinct from stage
 * (the employer's own review pipeline). This changes rarely and only
 * in one direction (active -> one terminal state).
 */
enum ApplicationOutcomeStatus: string
{
    case Active = 'active';
    case Withdrawn = 'withdrawn';
    case Hired = 'hired';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Withdrawn => 'Withdrawn',
            self::Hired => 'Hired',
            self::Rejected => 'Rejected',
        };
    }
}
