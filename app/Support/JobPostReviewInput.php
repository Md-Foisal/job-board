<?php

namespace App\Support;

use App\Ai\Agents\JobPostReviewer;
use App\Enums\SkillImportance;
use App\Models\JobPosting;
use App\Models\Skill;

/**
 * What is sent to the AI to review a job posting, and the cache key its
 * answer is kept under.
 *
 * The posting goes out as the employer wrote it, with the product's own
 * totals for it. Nothing about any applicant does: no names, CVs, answers
 * or individual scores, only counts -- the same counts every member of
 * the company sees on the analytics page.
 *
 * The key hashes the posting's part with the prompt's version, so a
 * review is shown again only while the posting and the prompt are as
 * they were when it was written. Hashing the content rather than the
 * posting's updated_at also catches an edit that only changes its
 * skills, which is saved to another table and leaves updated_at alone.
 * The totals are left out of the key: they move with every view, and a
 * review a few hours old is still a review of this posting.
 */
final class JobPostReviewInput
{
    /**
     * The period of totals the AI reads, whatever range the page shows,
     * so one version of a posting needs one review.
     */
    public const RANGE_DAYS = 30;

    /**
     * Enough for any real job description; the rest is cut so one very
     * long posting cannot make a run expensive.
     */
    private const DESCRIPTION_MAX = 12000;

    /**
     * @return array{job_posting: array<string, mixed>, performance: array<string, mixed>}
     */
    public static function for(JobPosting $jobPosting, JobPerformanceReport $report): array
    {
        return [
            'job_posting' => self::posting($jobPosting),
            'performance' => [
                'period_days' => $report->days,
                'status' => $report->job['status'] ?? null,
                'live_days' => $report->job['live_days'] ?? null,
                'views' => $report->views,
                'views_counted_for_whole_period' => $report->viewsCoverRange(),
                'applications' => $report->applications,
                'applications_per_hundred_views' => $report->applyRate,
                'applications_rejected_without_being_opened' => $report->rejectedUnseen,
                'applicants_by_skill_match' => $report->skillMatch === null ? null : [
                    'strong_70_percent_and_up' => $report->skillMatch['high'],
                    'partial_40_to_69_percent' => $report->skillMatch['medium'],
                    'weak_under_40_percent' => $report->skillMatch['low'],
                ],
            ],
        ];
    }

    /**
     * The whole input as one JSON object, which is what the model receives.
     * Whatever the employer typed arrives as a quoted string inside it;
     * angle brackets are escaped as well, so nothing in it can read as
     * markup around it.
     *
     * @param  array<string, array<string, mixed>>  $input
     */
    public static function material(array $input): string
    {
        return json_encode($input, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function cacheKey(JobPosting $jobPosting): string
    {
        $hash = hash('sha256', JobPostReviewer::VERSION.'|'.json_encode(self::posting($jobPosting), JSON_THROW_ON_ERROR));

        return "ai-job-review:{$jobPosting->id}:{$hash}";
    }

    /**
     * @return array<string, mixed>
     */
    private static function posting(JobPosting $jobPosting): array
    {
        // Read afresh: a page may have loaded the skills with their ids only.
        $skills = $jobPosting->skills()->orderBy('name')->get();
        $required = fn (Skill $skill) => $skill->pivot->importance === SkillImportance::Required;

        return [
            'title' => $jobPosting->title,
            'employment_type' => $jobPosting->employment_type->label(),
            'workplace_type' => $jobPosting->workplace_type->label(),
            'location' => collect([$jobPosting->location_city, $jobPosting->location_country])->filter()->implode(', ') ?: null,
            'salary' => [
                'min' => $jobPosting->salary_min,
                'max' => $jobPosting->salary_max,
                'currency' => $jobPosting->salary_currency,
                'period' => $jobPosting->salary_period?->label(),
                'negotiable' => (bool) $jobPosting->salary_negotiable,
            ],
            'min_experience_years' => $jobPosting->min_experience_years,
            'required_skills' => $skills->filter($required)->pluck('name')->values()->all(),
            'nice_to_have_skills' => $skills->reject($required)->pluck('name')->values()->all(),
            'screening_questions' => $jobPosting->screeningQuestions()->count(),
            'description' => RichText::plain($jobPosting->description, self::DESCRIPTION_MAX),
        ];
    }
}
