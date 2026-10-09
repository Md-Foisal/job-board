<?php

namespace App\Enums;

use App\Models\JobPosting;
use App\Models\Report;

/**
 * Where a posting stands for its own company, as one answer. The company
 * asks one question of a posting -- can candidates see it, and if not,
 * why not -- so it gets one state rather than its lifecycle and its
 * moderation side by side, which read "Active" next to "Hidden for
 * review" for a posting nobody could find.
 *
 * Checked in order: what the company itself did (a draft, closed) comes
 * first, then the date, then what our team holds back. Only Live is a
 * posting candidates can find.
 */
enum PostingState: string
{
    case Draft = 'draft';
    case Closed = 'closed';
    case Expired = 'expired';
    case NeedsChanges = 'needs-changes';
    case InReview = 'in-review';
    case HiddenForReview = 'hidden-for-review';
    case Live = 'live';

    /**
     * Uses open_reporters_count when a list loaded it with the postings,
     * so a table of them costs no query per row; asks otherwise.
     */
    public static function of(JobPosting $jobPosting): self
    {
        $hidden = fn (): bool => array_key_exists('open_reporters_count', $jobPosting->getAttributes())
            ? $jobPosting->open_reporters_count >= Report::HIDE_AFTER_REPORTERS
            : $jobPosting->isHiddenByReports();

        return match (true) {
            $jobPosting->availability_status === AvailabilityStatus::Draft => self::Draft,
            $jobPosting->availability_status === AvailabilityStatus::Closed => self::Closed,
            $jobPosting->availability_status === AvailabilityStatus::Expired, $jobPosting->isExpired() => self::Expired,
            $jobPosting->moderation_status === ModerationStatus::Rejected => self::NeedsChanges,
            $jobPosting->moderation_status === ModerationStatus::Pending => self::InReview,
            $hidden() => self::HiddenForReview,
            default => self::Live,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Closed => 'Closed',
            self::Expired => 'Expired',
            self::NeedsChanges => 'Needs changes',
            self::InReview => 'In review',
            self::HiddenForReview => 'Hidden for review',
            self::Live => 'Live',
        };
    }

    /**
     * The status colours and nothing else: green is the one state that
     * works, red is the one the company has to fix, amber is waiting on
     * our team, and grey is a posting the company has put away itself.
     */
    public function color(): string
    {
        return match ($this) {
            self::Live => 'green',
            self::NeedsChanges => 'red',
            self::InReview, self::HiddenForReview => 'amber',
            self::Draft, self::Closed, self::Expired => 'zinc',
        };
    }
}
