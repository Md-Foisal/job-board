<?php

namespace App\Enums;

/**
 * Staff review before something goes public: a job posting (separate
 * from its employer-facing draft/active/expired/closed status), and a
 * company review and the company's answer to it, each on its own.
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
