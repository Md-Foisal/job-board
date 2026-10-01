<?php

namespace App\Support;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\CompanyReview;
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

    /**
     * The candidate already has a review of this company, from this
     * application or another one: one voice per person per company. A
     * newer experience goes into that review by editing it.
     */
    public const ALREADY_REVIEWED = 'already_reviewed';

    /**
     * Anyone who is or was on the company's team. They saw the hiring
     * process from the inside, so their account is not an applicant's,
     * whatever badge it would carry.
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

    /**
     * How a reason reads to staff checking a review's proof.
     */
    public static function describe(string $reason): string
    {
        return match ($reason) {
            self::DECIDED => 'Told of a hire or rejection',
            self::INTERVIEWED => 'Reached the interview stage',
            self::UNANSWERED => 'No reply in the first '.self::UNANSWERED_DAYS.' days',
            self::ALREADY_REVIEWED => 'Already reviewed this company',
            self::INSIDER => 'Is or was on the company team',
            self::WITHDRAWN => 'Withdrew before anything happened',
            self::IN_PROGRESS => 'Nothing yet to review',
        };
    }

    public static function of(Application $application): self
    {
        $hasReviewed = CompanyReview::query()
            ->where('company_id', $application->jobPosting->company_id)
            ->where('candidate_profile_id', $application->candidate_profile_id)
            ->exists();

        if ($hasReviewed) {
            return new self(self::ALREADY_REVIEWED);
        }

        if ($application->candidateProfile->user->hasWorkedAt($application->jobPosting->company)) {
            return new self(self::INSIDER);
        }

        return new self(self::basisOf($application));
    }

    /**
     * The candidate's most recent application to the company that gives
     * them something to describe -- the one their single review of that
     * company is about. Null if none does yet.
     */
    public static function latestQualifying(int $candidateProfileId, int $companyId): ?Application
    {
        return Application::query()
            ->where('candidate_profile_id', $candidateProfileId)
            ->whereRelation('jobPosting', 'company_id', $companyId)
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->first(fn (Application $application) => in_array(
                self::basisOf($application),
                [self::DECIDED, self::INTERVIEWED, self::UNANSWERED],
                true,
            ));
    }

    public function allows(): bool
    {
        return in_array($this->reason, [self::DECIDED, self::INTERVIEWED, self::UNANSWERED], true);
    }

    /**
     * What the application itself gives the candidate to describe, apart
     * from who they are and what they have already written. Staff read it
     * as the proof behind a review.
     */
    public static function basisOf(Application $application): string
    {
        // A decision still inside its undo window has not reached the
        // candidate yet, so it cannot be what they are reviewing.
        if (in_array($application->outcomeForCandidate(), [ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected], true)) {
            return self::DECIDED;
        }

        if (self::reachedInterview($application)) {
            return self::INTERVIEWED;
        }

        if (self::wentUnanswered($application)) {
            return self::UNANSWERED;
        }

        return $application->outcome_status === ApplicationOutcomeStatus::Withdrawn
            ? self::WITHDRAWN
            : self::IN_PROGRESS;
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
