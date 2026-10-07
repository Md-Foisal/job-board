<?php

namespace App\Enums;

use App\Models\Application;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where an application stands, in the four words a candidate tracks it
 * by (Indeed's "My jobs" splits the same way): sent and waiting, being
 * looked at, an offer on the table, or over. Built from the employer's
 * stage and the outcome, as the candidate may see them -- a decision
 * still inside its undo window has not been made yet, as far as they
 * are concerned.
 */
enum CandidateApplicationStatus: string
{
    case Applied = 'applied';
    case InReview = 'in-review';
    case Offer = 'offer';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::InReview => 'In review',
            self::Offer => 'Offer',
            self::Closed => 'Closed',
        };
    }

    public static function of(Application $application): self
    {
        if ($application->outcomeForCandidate() !== ApplicationOutcomeStatus::Active) {
            return self::Closed;
        }

        return match ($application->stage) {
            ApplicationStage::New => self::Applied,
            ApplicationStage::Shortlisted, ApplicationStage::Interview => self::InReview,
            ApplicationStage::Offer => self::Offer,
        };
    }

    /**
     * The same split as a query, for a list filtered to one of them.
     */
    public function scope(Builder $query): Builder
    {
        $openForCandidate = fn (Builder $query) => $query
            ->where('outcome_status', ApplicationOutcomeStatus::Active)
            ->orWhere(fn (Builder $query) => $query
                ->whereIn('outcome_status', [ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected])
                ->where('decided_at', '>', now()->subMinutes(Application::UNDO_MINUTES)));

        if ($this === self::Closed) {
            return $query->whereNot($openForCandidate);
        }

        $stages = match ($this) {
            self::Applied => [ApplicationStage::New],
            self::InReview => [ApplicationStage::Shortlisted, ApplicationStage::Interview],
            self::Offer => [ApplicationStage::Offer],
        };

        return $query->where($openForCandidate)->whereIn('stage', $stages);
    }
}
