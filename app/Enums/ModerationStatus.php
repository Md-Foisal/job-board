<?php

namespace App\Enums;

/**
 * Admin/moderation workflow for a job posting, separate from its
 * employer-facing availability status (draft/active/expired/closed).
 */
enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
