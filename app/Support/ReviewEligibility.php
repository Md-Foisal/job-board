<?php

namespace App\Support;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Services\JobPerformance;
use Carbon\CarbonImmutable;

/**
 * Whether an application entitles its candidate to review the company's
 * hiring process, and the reason either way.
 *
 * A review has to describe something the writer went through: a
 * decision, an interview, or a month of hearing nothing at all. Each of
 * those, once it has happened, stays true whatever the company does
 * afterwards -- a late stage move cannot take back a month of silence,
 * so it cannot take away the right to describe it either.
 */
final readonly class ReviewEligibility
{
    /**
     * How long an application can go without any reply from the company
     * before the silence itself is worth reviewing.
     */
    public const UNANSWERED_DAYS = 30;

    public const DECIDED = 'decided';

    public const INTERVIEWED = 'interviewed';

    public const UNANSWERED = 'unanswered';

    public const ALREADY_REVIEWED = 'already_reviewed';

    /**
     * Anyone who is or was on the company's team: an undisclosed insider
     * review is exactly the kind of fake review the rules are there for.
     */
    public const INSIDER = 'insider';

    /**
     * Withdrawn before anything worth reviewing happened.
     */
    public const WITHDRAWN = 'withdrawn';

    /**
     * Still open, with nothing yet that qualifies. May change.
     */
    public const IN_PROGRESS = 'in_progress';

    private function __construct(public string $reason) {}

    public static function of(Application $application): self
    {
        if ($application->review()->exists()) {
            return new self(self::ALREADY_REVIEWED);
        }

        if ($application->candidateProfile->user->hasWorkedAt($application->jobPosting->company)) {
            return new self(self::INSIDER);
        }

        // A decision still inside its undo window has not reached the
        // candidate yet, so it cannot be what they are reviewing.
        if (in_array($application->outcomeForCandidate(), [ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected], true)) {
            return new self(self::DECIDED);
        }

        if (self::reachedInterview($application)) {
            return new self(self::INTERVIEWED);
        }

        if (self::wentUnanswered($application)) {
            return new self(self::UNANSWERED);
        }

        return new self($application->outcome_status === ApplicationOutcomeStatus::Withdrawn
            ? self::WITHDRAWN
            : self::IN_PROGRESS);
    }

    public function allows(): bool
    {
        return in_array($this->reason, [self::DECIDED, self::INTERVIEWED, self::UNANSWERED], true);
    }

    /**
     * At the interview stage or beyond, now or at any point: moving an
     * interviewed candidate back does not undo the interview.
     */
    private static function reachedInterview(Application $application): bool
    {
        $stages = [ApplicationStage::Interview->value, ApplicationStage::Offer->value];

        return in_array($application->stage->value, $stages, true)
            || $application->events()->whereIn('to_stage', $stages)->exists();
    }

    /**
     * No reply from the company in the first month, by the same measure
     * of "reply" the employer's own analytics use. A candidate who gave up
     * and withdrew after that month still went through it; one who
     * withdrew sooner did not.
     */
    private static function wentUnanswered(Application $application): bool
    {
        $deadline = CarbonImmutable::parse($application->created_at)->addDays(self::UNANSWERED_DAYS);

        if (now()->lt($deadline)) {
            return false;
        }

        $firstResponse = JobPerformance::firstResponses(Application::query()->whereKey($application->id))
            ->get($application->id);

        if ($firstResponse !== null && $firstResponse->lt($deadline)) {
            return false;
        }

        $withdrawnAt = $application->events()
            ->where('to_outcome_status', ApplicationOutcomeStatus::Withdrawn->value)
            ->min('created_at');

        return $withdrawnAt === null || CarbonImmutable::parse($withdrawnAt)->gte($deadline);
    }
}
