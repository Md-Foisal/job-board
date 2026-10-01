<?php

namespace App\Services;

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\Company;
use App\Support\PublicCache;
use Carbon\CarbonImmutable;

/**
 * Whether a company answers the people who apply to it: the "Responsive
 * employer" mark on its page and on its job postings.
 *
 * The mark is recognition only. A company that falls short shows nothing,
 * exactly like one too new to measure, so its absence says nothing a
 * reader could hold against a small employer.
 *
 * An answer is the company's first change to an application, a stage move
 * or a decision, taken from the same query the company's own analytics
 * use, so the two can never disagree about what counts.
 */
class EmployerResponsiveness
{
    /**
     * How many days of applications are looked at.
     */
    public const WINDOW_DAYS = 90;

    /**
     * How long a company has to answer an application.
     */
    public const ANSWER_WITHIN_DAYS = 14;

    /**
     * Below this many applications a share says too little to go by.
     */
    public const MIN_APPLICATIONS = 10;

    /**
     * The share that has to have been answered in time.
     */
    public const RESPONSIVE_FROM_PERCENT = 75;

    /**
     * Answers do not move the public cache, so this is how long a change
     * can take to show: an hour is close enough for a rolling 90 days.
     */
    private const CACHE_SECONDS = 3600;

    /**
     * The share answered in time, in whole percent rounded down, when the
     * company has earned the mark; null otherwise. A failure here costs
     * the mark and is reported, never the page it sits on.
     */
    public function percentFor(Company $company): ?int
    {
        return rescue(function () use ($company) {
            $measure = PublicCache::remember(
                "employer-responsiveness:{$company->id}",
                fn () => $this->measure($company),
                self::CACHE_SECONDS,
            );

            return self::percentIfResponsive($measure['answered'], $measure['counted']);
        });
    }

    /**
     * Counts the applications that arrived during the 90 days ending two
     * weeks ago, so that every one of them has had its full fourteen days.
     * Taking in the last two weeks as well would count the newest
     * applications as unanswered before the company's time is up.
     *
     * An application the candidate withdrew before the company's time was
     * up, with no answer yet, is left out: the company never had its
     * chance to answer it. A withdrawal is never an answer either way.
     *
     * @return array{counted: int, answered: int}
     */
    public function measure(Company $company): array
    {
        $until = CarbonImmutable::now()->subDays(self::ANSWER_WITHIN_DAYS);
        $from = $until->subDays(self::WINDOW_DAYS);

        $cohort = Application::query()
            ->whereIn('job_posting_id', $company->jobPostings()->select('id'))
            ->where('created_at', '>', $from)
            ->where('created_at', '<=', $until);

        $appliedAt = $cohort->clone()->toBase()->pluck('created_at', 'id');

        $answers = JobPerformance::firstResponses($cohort);

        $withdrawals = ApplicationEvent::query()
            ->whereIn('application_id', $cohort->clone()->select('applications.id'))
            ->where('to_outcome_status', ApplicationOutcomeStatus::Withdrawn->value)
            ->groupBy('application_id')
            ->selectRaw('application_id, min(created_at) as withdrawn_at')
            ->pluck('withdrawn_at', 'application_id');

        $counted = 0;
        $answered = 0;

        foreach ($appliedAt as $id => $createdAt) {
            $deadline = CarbonImmutable::parse($createdAt)->addDays(self::ANSWER_WITHIN_DAYS);
            $answer = $answers->get($id);

            if ($answer !== null && $answer->lte($deadline)) {
                $counted++;
                $answered++;

                continue;
            }

            $withdrawnAt = $withdrawals->get($id);

            if ($withdrawnAt !== null && CarbonImmutable::parse($withdrawnAt)->lt($deadline)) {
                continue;
            }

            $counted++;
        }

        return ['counted' => $counted, 'answered' => $answered];
    }

    /**
     * Compared in whole numbers, so that exactly three in four is never
     * lost to a rounding error.
     */
    public static function percentIfResponsive(int $answered, int $counted): ?int
    {
        if ($counted < self::MIN_APPLICATIONS) {
            return null;
        }

        return $answered * 100 >= self::RESPONSIVE_FROM_PERCENT * $counted
            ? intdiv($answered * 100, $counted)
            : null;
    }
}
