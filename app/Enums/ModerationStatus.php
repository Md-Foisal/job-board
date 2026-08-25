<?php

namespace App\Enums;

/**
 * Admin/moderation workflow for a job listing, separate from the
 * employer-facing `status` (open/closed). Not yet surfaced in any UI —
 * paired with the "approved only" public-listing filter and the Filament
 * moderation panel planned for Phase C/D.
 */
enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
