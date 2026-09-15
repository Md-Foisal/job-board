<?php

namespace App\Enums;

/**
 * The employer's own review pipeline for an application -- distinct
 * from outcome_status. Every application starts at New.
 */
enum ApplicationStage: string
{
    case New = 'new';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Offer = 'offer';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Shortlisted => 'Shortlisted',
            self::Interview => 'Interview',
            self::Offer => 'Offer',
        };
    }
}
