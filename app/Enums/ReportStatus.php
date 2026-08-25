<?php

namespace App\Enums;

/**
 * Lifecycle of a user-submitted report (polymorphic, on job listings/etc.).
 * Not yet surfaced in any UI — paired with the Report button + Moderation UI
 * backlog item.
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reviewed => 'Reviewed',
            self::Dismissed => 'Dismissed',
        };
    }
}
