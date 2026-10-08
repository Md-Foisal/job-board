<?php

namespace App\Services;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\JobPosting;
use App\Support\ExperienceDuration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One job's applicants, scored and put in order -- the list a company
 * works through. The applications page shows it, and the applicant page
 * walks it with previous and next, so both read the order from here and
 * "next" is always the row under the one just opened.
 *
 * A stage holds the applications still open there, as the dashboard
 * counts them: one turned down or withdrawn at Interview is no longer in
 * interview. "all" holds every application, decided ones included.
 */
class JobApplicants
{
    public const SORTS = ['match', 'experience', 'newest', 'oldest'];

    public function __construct(private MatchScoreCalculator $calculator) {}

    /**
     * @return Collection<int, Application> each with match_score set
     */
    public function list(JobPosting $jobPosting, string $stage = 'all', string $sort = 'match'): Collection
    {
        $applications = $this->query($jobPosting, $stage)
            ->with([
                'candidateProfile.user:id,name,email,avatar,anonymized_at',
                // Needed for the match percentage, and easy to forget: without
                // it the page would run one query per applicant to answer the
                // question it exists to answer.
                'candidateProfile.skills:id',
            ])
            ->when($sort === 'experience', fn (Builder $query) => $query->with(
                'candidateProfile.experienceRecords:id,candidate_profile_id,start_date,end_date',
            ))
            ->get();

        $applications->each(function (Application $application) use ($jobPosting, $sort) {
            $application->setRelation('jobPosting', $jobPosting);
            $application->match_score = $this->calculator->calculate(
                $jobPosting,
                $application->candidateProfile->skills->pluck('id'),
            );

            if ($sort === 'experience') {
                $application->experience_months = ExperienceDuration::months($application->candidateProfile->experienceRecords);
            }
        });

        // Ties keep the order people applied in, so two equal scores never
        // swap places between one page load and the next.
        $byDate = $applications->sortBy(fn (Application $a) => [$a->created_at->getTimestamp(), $a->id]);

        return match ($sort) {
            'newest' => $byDate->reverse()->values(),
            'oldest' => $byDate->values(),
            // Total working time on the profile, overlapping roles counted
            // once -- the same months the candidate's own match check uses.
            'experience' => $byDate->sortByDesc(fn (Application $a) => $a->experience_months)->values(),
            default => $byDate->sortByDesc(fn (Application $a) => $a->match_score ?? -1)->values(),
        };
    }

    /**
     * How many applications each tab holds: every one under "all", and
     * the open ones at each stage.
     *
     * @return array<string, int>
     */
    public function counts(JobPosting $jobPosting): array
    {
        $open = $jobPosting->applications()
            ->where('outcome_status', ApplicationOutcomeStatus::Active)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $counts = ['all' => $jobPosting->applications()->count()];

        foreach (ApplicationStage::cases() as $stage) {
            $counts[$stage->value] = (int) ($open[$stage->value] ?? 0);
        }

        return $counts;
    }

    /**
     * The applications either side of one, in the order the list shows.
     * When the application has left the list it was opened from -- moved
     * on to another stage, or decided -- the walk continues through all of
     * the job's applications instead, so the buttons never go dead.
     *
     * @return array{previous: ?Application, next: ?Application, position: int, total: int}
     */
    public function around(Application $application, string $stage = 'all', string $sort = 'match'): array
    {
        $jobPosting = $application->jobPosting;
        $list = $this->list($jobPosting, $stage, $sort);
        $index = $list->search(fn (Application $a) => $a->is($application));

        if ($index === false && $stage !== 'all') {
            $list = $this->list($jobPosting, 'all', $sort);
            $index = $list->search(fn (Application $a) => $a->is($application));
        }

        if ($index === false) {
            return ['previous' => null, 'next' => null, 'position' => 0, 'total' => $list->count()];
        }

        return [
            'previous' => $list->get($index - 1),
            'next' => $list->get($index + 1),
            'position' => $index + 1,
            'total' => $list->count(),
        ];
    }

    public static function isStage(string $stage): bool
    {
        return $stage === 'all' || ApplicationStage::tryFrom($stage) !== null;
    }

    private function query(JobPosting $jobPosting, string $stage): Builder
    {
        return Application::query()
            ->where('job_posting_id', $jobPosting->id)
            ->when($stage !== 'all', fn (Builder $query) => $query
                ->where('stage', $stage)
                ->where('outcome_status', ApplicationOutcomeStatus::Active));
    }
}
