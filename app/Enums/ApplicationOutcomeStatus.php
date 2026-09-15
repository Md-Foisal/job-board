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

    /**
     * The Flux badge colour for this status. It sits next to label()
     * rather than in a view so that a new case fails here, once, in the
     * place that already had to be edited -- instead of silently
     * rendering the wrong colour on whichever page was forgotten.
     * Deliberately has no default arm for the same reason.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'blue',
            self::Hired => 'green',
            self::Rejected => 'red',
            self::Withdrawn => 'zinc',
        };
    }
}
