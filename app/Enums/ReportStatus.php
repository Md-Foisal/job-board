<?php

namespace App\Enums;

/**
 * Lifecycle of a user-submitted report against a job posting or company.
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Actioned = 'actioned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reviewed => 'Reviewed',
            self::Actioned => 'Actioned',
        };
    }
}
