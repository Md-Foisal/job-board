<?php

namespace App\Services;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\AvailabilityStatus;
use App\Enums\ModerationStatus;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use App\Support\JobPerformanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * How a company's postings are doing: who looked, who applied, how far
 * applicants got, and how quickly the company answered them.
 *
 * Applications are followed as a cohort: the funnel, the skill-match
 * spread and the first-response time are about the applications that
 * arrived in the range, however long afterwards each one moved on. Views,
 * hires and the time to hire are counted on the day they happened.
 *
 * Days are the company's own, in its time zone, so everyone on the team
 * sees the same numbers wherever they are; daily views are stored that
 * way when they are counted (RecordJobView).
 *
 * Every figure comes from our own tables; nothing here is sent anywhere.
 */
class JobPerformance
{
    public const RANGES = [7, 30, 90];

    public const DEFAULT_RANGE = 30;

    /**
     * Below this many views an apply rate swings wildly on one applicant.
     */
    public const MIN_VIEWS_FOR_RATE = 20;

    /**
     * A median of one or two values is just those values.
     */
    public const MIN_VALUES_FOR_MEDIAN = 3;

    private const CACHE_SECONDS = 600;

    /**
     * Skill-match scores from this value up fall in the bucket.
     */
    private const MEDIUM_MATCH_FROM = 40;

    private const HIGH_MATCH_FROM = 70;

    private const STAGE_ORDER = ['new' => 0, 'shortlisted' => 1, 'interview' => 2, 'offer' => 3];

    private const HIRED_RANK = 4;

    public function __construct(private MatchScoreCalculator $calculator) {}

    public function for(Company $company, ?JobPosting $jobPosting = null, int $days = self::DEFAULT_RANGE): JobPerformanceReport
    {
        if ($jobPosting !== null && $jobPosting->company_id !== $company->id) {
            throw new InvalidArgumentException('The job posting belongs to another company.');
        }

        if (! in_array($days, self::RANGES, true)) {
            throw new InvalidArgumentException("Unsupported range of {$days} days.");
        }

        $key = 'job-performance:'.$company->id.':'.($jobPosting->id ?? 'all').':'.$days.':'.$company->timezone;

        return JobPerformanceReport::fromArray(
            Cache::remember($key, self::CACHE_SECONDS, fn () => $this->build($company, $jobPosting, $days)->toArray()),
        );
    }

    /**
     * When each application first heard from the company: the earliest
     * change the company made to it, a stage move or a decision.
     *
     * Defined by what the change is, not by who made it. The candidate's
     * only change is withdrawing, so a withdrawal is never an answer, and
     * that stays true when an account behind an event is later erased. A
     * decision that was undone is no answer either.
     * The company-wide responsiveness figure reads this same method.
     *
     * @param  Builder<Application>  $applications
     * @return Collection<int, CarbonImmutable> application id => first answer
     */
    public static function firstResponses(Builder $applications): Collection
    {
        return ApplicationEvent::query()
            ->whereIn('application_id', $applications->clone()->select('applications.id'))
            ->where(fn (Builder $query) => $query
                ->whereNotNull('to_stage')
                ->orWhere(fn (Builder $decision) => $decision->standingDecisions(
                    ApplicationOutcomeStatus::Hired,
                    ApplicationOutcomeStatus::Rejected,
                )))
            ->groupBy('application_id')
            ->selectRaw('application_id, min(created_at) as first_response_at')
            ->pluck('first_response_at', 'application_id')
            ->map(fn ($at) => CarbonImmutable::parse($at));
    }

    private function build(Company $company, ?JobPosting $jobPosting, int $days): JobPerformanceReport
    {
        $zone = $company->timezone;

        // $from is the first day's midnight in the company's zone; queries
        // take it as the UTC moment it is, since that is what is stored.
        $from = CarbonImmutable::today($zone)->subDays($days - 1);
        $start = $from->utc();
        $now = CarbonImmutable::now();

        $jobIds = $jobPosting !== null
            ? collect([$jobPosting->id])
            : $company->jobPostings()->pluck('id');

        $inScope = fn () => Application::query()->whereIn('job_posting_id', $jobIds);

        $cohortQuery = $inScope()->whereBetween('created_at', [$start, $now]);

        $cohort = $cohortQuery->clone()
            ->with([
                'jobPosting:id',
                'jobPosting.skills:id',
                'candidateProfile:id',
                'candidateProfile.skills:id',
            ])
            ->get(['id', 'job_posting_id', 'candidate_profile_id', 'stage', 'outcome_status', 'created_at']);

        $events = ApplicationEvent::query()
            ->whereIn('application_id', $cohortQuery->clone()->select('id'))
            ->get(['application_id', 'to_stage', 'to_outcome_status'])
            ->groupBy('application_id');

        $dailyViews = $this->dailyViews($jobIds, $from, $days);
        $views = array_sum($dailyViews);

        $firstResponses = self::firstResponses($cohortQuery->clone());

        $responseHours = $cohort
            ->filter(fn (Application $application) => $firstResponses->has($application->id))
            ->map(fn (Application $application) => round($application->created_at->diffInMinutes($firstResponses[$application->id]) / 60, 1));

        $hireDays = $this->hireDays($jobIds, $start, $now);

        $waiting = $inScope()
            ->where('outcome_status', ApplicationOutcomeStatus::Active)
            ->where('stage', ApplicationStage::New)
            // An open application can carry no standing decision, so a
            // stage move is the only touch left to look for; an undone
            // decision is no touch.
            ->whereDoesntHave('events', fn (Builder $events) => $events->whereNotNull('to_stage'));

        $oldestWaiting = $waiting->clone()->min('created_at');

        return new JobPerformanceReport(
            days: $days,
            from: $from,
            views: $views,
            viewsCountedSince: $this->viewsCountedSince($jobIds),
            dailyViews: $dailyViews,
            applications: $cohort->count(),
            dailyApplications: $this->dailyApplications($cohort, $from, $days, $zone),
            applyRate: $views >= self::MIN_VIEWS_FOR_RATE ? round($cohort->count() / $views * 100, 1) : null,
            funnel: $this->funnel($cohort, $events),
            rejectedUnseen: $this->rejectedUnseen($cohort, $events),
            skillMatch: $this->skillMatch($cohort),
            responded: $responseHours->count(),
            firstResponseMedianHours: $this->median($responseHours),
            waiting: $waiting->count(),
            oldestWaitingSince: $oldestWaiting !== null ? CarbonImmutable::parse($oldestWaiting) : null,
            hires: $hireDays->count(),
            timeToHireMedianDays: $this->median($hireDays),
            timeToFillDays: $this->timeToFill($jobIds, $zone),
            saves: DB::table('saved_jobs')->whereIn('job_posting_id', $jobIds)->count(),
            job: $jobPosting !== null ? $this->jobState($jobPosting, $now, $zone) : null,
        );
    }

    /**
     * @return array<string, int>
     */
    private function dailyViews(Collection $jobIds, CarbonImmutable $from, int $days): array
    {
        $counted = JobPostingDailyStat::query()
            ->whereIn('job_posting_id', $jobIds)
            ->where('date', '>=', $from->toDateString())
            ->groupBy('date')
            ->selectRaw('date, sum(views) as views')
            ->pluck('views', 'date')
            ->mapWithKeys(fn ($views, $date) => [CarbonImmutable::parse($date)->toDateString() => (int) $views]);

        return $this->everyDay($from, $days, fn (string $date) => $counted[$date] ?? 0);
    }

    private function viewsCountedSince(Collection $jobIds): ?CarbonImmutable
    {
        $first = JobPostingDailyStat::query()->whereIn('job_posting_id', $jobIds)->min('date');

        return $first !== null ? CarbonImmutable::parse($first) : null;
    }

    /**
     * @return array<string, int>
     */
    private function dailyApplications(Collection $cohort, CarbonImmutable $from, int $days, string $zone): array
    {
        $counted = $cohort->countBy(fn (Application $application) => $application->created_at->setTimezone($zone)->toDateString());

        return $this->everyDay($from, $days, fn (string $date) => $counted[$date] ?? 0);
    }

    /**
     * @return array<string, int>
     */
    private function everyDay(CarbonImmutable $from, int $days, callable $value): array
    {
        $series = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $from->addDays($day)->toDateString();
            $series[$date] = $value($date);
        }

        return $series;
    }

    /**
     * How many of the cohort reached each step, at any point, by the
     * furthest step each one reached. A step skipped on the way (new
     * straight to interview) still counts as passed, so each bar is never
     * longer than the one before it.
     *
     * @return array{shortlisted: int, interview: int, offer: int, hired: int}
     */
    private function funnel(Collection $cohort, Collection $events): array
    {
        $furthest = $cohort->map(function (Application $application) use ($events) {
            $applicationEvents = $events->get($application->id, collect());

            // The current outcome, not the history: a hire that was undone
            // is not a hire.
            if ($application->outcome_status === ApplicationOutcomeStatus::Hired) {
                return self::HIRED_RANK;
            }

            return $applicationEvents
                ->pluck('to_stage')
                ->filter()
                ->push($application->stage->value)
                ->map(fn (string $stage) => self::STAGE_ORDER[$stage] ?? 0)
                ->max();
        });

        return [
            'shortlisted' => $furthest->filter(fn (int $rank) => $rank >= 1)->count(),
            'interview' => $furthest->filter(fn (int $rank) => $rank >= 2)->count(),
            'offer' => $furthest->filter(fn (int $rank) => $rank >= 3)->count(),
            'hired' => $furthest->filter(fn (int $rank) => $rank >= self::HIRED_RANK)->count(),
        ];
    }

    /**
     * Rejected while still at New, never moved to any stage: turned down
     * without being looked at, which is what an employer drowning in
     * applications that do not fit ends up doing.
     */
    private function rejectedUnseen(Collection $cohort, Collection $events): int
    {
        return $cohort
            ->filter(fn (Application $application) => $application->outcome_status === ApplicationOutcomeStatus::Rejected
                && $application->stage === ApplicationStage::New
                && ! $events->get($application->id, collect())->contains(fn ($event) => $event->to_stage !== null))
            ->count();
    }

    /**
     * The same score the employer sees beside each application, grouped.
     * Applications to a posting without skills, or from a candidate who
     * listed none, have no score and are left out; with none left there
     * is nothing to show.
     *
     * @return ?array{low: int, medium: int, high: int}
     */
    private function skillMatch(Collection $cohort): ?array
    {
        $scores = $cohort
            ->map(fn (Application $application) => $this->calculator->calculate(
                $application->jobPosting,
                $application->candidateProfile->skills->pluck('id'),
            ))
            ->filter(fn (?int $score) => $score !== null);

        if ($scores->isEmpty()) {
            return null;
        }

        return [
            'low' => $scores->filter(fn (int $score) => $score < self::MEDIUM_MATCH_FROM)->count(),
            'medium' => $scores->filter(fn (int $score) => $score >= self::MEDIUM_MATCH_FROM && $score < self::HIGH_MATCH_FROM)->count(),
            'high' => $scores->filter(fn (int $score) => $score >= self::HIGH_MATCH_FROM)->count(),
        ];
    }

    /**
     * Days from application to hire, for the hires made in the range --
     * counted when the hire happened, however long ago they applied.
     *
     * @return Collection<int, float>
     */
    private function hireDays(Collection $jobIds, CarbonImmutable $start, CarbonImmutable $now): Collection
    {
        return ApplicationEvent::query()
            ->join('applications', 'applications.id', '=', 'application_events.application_id')
            ->whereIn('applications.job_posting_id', $jobIds)
            ->standingDecisions(ApplicationOutcomeStatus::Hired)
            ->whereBetween('application_events.created_at', [$start, $now])
            ->get(['applications.created_at as applied_at', 'application_events.created_at as hired_at'])
            ->map(fn ($hire) => round(CarbonImmutable::parse($hire->applied_at)->diffInHours(CarbonImmutable::parse($hire->hired_at)) / 24, 1));
    }

    /**
     * For each posting that has a hire: days from publishing to the first
     * one. Not tied to the range -- a posting is filled once.
     *
     * @return array<int, int>
     */
    private function timeToFill(Collection $jobIds, string $zone): array
    {
        $firstHires = ApplicationEvent::query()
            ->join('applications', 'applications.id', '=', 'application_events.application_id')
            ->whereIn('applications.job_posting_id', $jobIds)
            ->standingDecisions(ApplicationOutcomeStatus::Hired)
            ->groupBy('applications.job_posting_id')
            ->selectRaw('applications.job_posting_id, min(application_events.created_at) as first_hire_at')
            ->pluck('first_hire_at', 'job_posting_id');

        return JobPosting::query()
            ->whereIn('id', $firstHires->keys())
            ->whereNotNull('published_at')
            ->get(['id', 'published_at'])
            ->mapWithKeys(fn (JobPosting $job) => [
                $job->id => (int) $job->published_at->setTimezone($zone)->startOfDay()
                    ->diffInDays(CarbonImmutable::parse($firstHires[$job->id])->setTimezone($zone)->startOfDay()),
            ])
            ->all();
    }

    /**
     * @return array{status: string, published_at: ?CarbonImmutable, live_days: ?int, expires_in_days: ?int}
     */
    private function jobState(JobPosting $jobPosting, CarbonImmutable $now, string $zone): array
    {
        $status = match (true) {
            $jobPosting->availability_status === AvailabilityStatus::Draft => 'draft',
            $jobPosting->availability_status === AvailabilityStatus::Closed => 'closed',
            $jobPosting->availability_status === AvailabilityStatus::Expired, $jobPosting->isExpired() => 'expired',
            $jobPosting->moderation_status === ModerationStatus::Pending => 'in_review',
            $jobPosting->moderation_status === ModerationStatus::Rejected => 'rejected',
            default => 'live',
        };

        $liveUntil = $jobPosting->expires_at->lt($now) ? $jobPosting->expires_at : $now;

        return [
            'status' => $status,
            'published_at' => $jobPosting->published_at,
            'live_days' => $jobPosting->published_at !== null
                ? (int) $jobPosting->published_at->setTimezone($zone)->startOfDay()->diffInDays($liveUntil->setTimezone($zone)->startOfDay())
                : null,
            'expires_in_days' => in_array($status, ['live', 'in_review'], true)
                ? (int) ceil($now->diffInHours($jobPosting->expires_at) / 24)
                : null,
        ];
    }

    private function median(Collection $values): ?float
    {
        if ($values->count() < self::MIN_VALUES_FOR_MEDIAN) {
            return null;
        }

        return round((float) $values->median(), 1);
    }
}
