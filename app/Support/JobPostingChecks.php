<?php

namespace App\Support;

use App\Enums\AvailabilityStatus;
use App\Enums\JobPostingGap;
use App\Enums\SkillImportance;
use App\Models\JobPosting;

/**
 * What a posting could do better, worked out on our own server for every
 * plan. The thresholds are our own rules of thumb, kept here in one
 * place, not figures from any outside study.
 *
 * The checks that read numbers (apply rate, rejections) take them from a
 * performance report for this one posting, and stay quiet until there is
 * enough behind them to mean something.
 */
final class JobPostingChecks
{
    public const MAX_REQUIRED_SKILLS = 10;

    public const MIN_DESCRIPTION_WORDS = 100;

    public const MAX_DESCRIPTION_WORDS = 1000;

    public const EXPIRING_WITHIN_DAYS = 3;

    public const LOW_RATE_MIN_VIEWS = 100;

    /**
     * Applications per hundred views below which the rate counts as low.
     */
    public const LOW_APPLY_RATE = 2.0;

    public const REJECTED_UNSEEN_MIN_APPLICATIONS = 10;

    public const REJECTED_UNSEEN_SHARE = 0.5;

    /**
     * @return array<int, JobPostingGap>
     */
    public static function for(JobPosting $jobPosting, ?JobPerformanceReport $report = null): array
    {
        $gaps = [];

        if ($jobPosting->salary_min === null && $jobPosting->salary_max === null) {
            $gaps[] = JobPostingGap::NoSalary;
        }

        $required = $jobPosting->skills
            ->filter(fn ($skill) => $skill->pivot->importance === SkillImportance::Required)
            ->count();

        if ($required > self::MAX_REQUIRED_SKILLS) {
            $gaps[] = JobPostingGap::TooManyRequiredSkills;
        }

        $words = self::wordCount($jobPosting->description);

        if ($words < self::MIN_DESCRIPTION_WORDS) {
            $gaps[] = JobPostingGap::ShortDescription;
        } elseif ($words > self::MAX_DESCRIPTION_WORDS) {
            $gaps[] = JobPostingGap::LongDescription;
        }

        if ($jobPosting->availability_status === AvailabilityStatus::Active
            && ! $jobPosting->isExpired()
            && $jobPosting->expires_at->lte(now()->addDays(self::EXPIRING_WITHIN_DAYS))) {
            $gaps[] = JobPostingGap::ExpiringSoon;
        }

        if ($report !== null && $report->job !== null) {
            if ($report->views >= self::LOW_RATE_MIN_VIEWS
                && $report->applyRate !== null
                && $report->applyRate < self::LOW_APPLY_RATE) {
                $gaps[] = JobPostingGap::LowApplyRate;
            }

            if ($report->applications >= self::REJECTED_UNSEEN_MIN_APPLICATIONS
                && $report->rejectedUnseen / $report->applications >= self::REJECTED_UNSEEN_SHARE) {
                $gaps[] = JobPostingGap::ManyRejectedUnseen;
            }
        }

        return $gaps;
    }

    /**
     * Words split on whitespace rather than counted by str_word_count(),
     * which only knows Latin letters and would find no words at all in a
     * posting written in Bengali or Arabic.
     */
    private static function wordCount(?string $description): int
    {
        $text = RichText::plain($description, PHP_INT_MAX);

        return $text === null ? 0 : count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    }
}
