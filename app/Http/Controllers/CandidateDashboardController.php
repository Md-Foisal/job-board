<?php

namespace App\Http\Controllers;

use App\Enums\CandidateApplicationStatus;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\CompanyReview;
use App\Models\JobPosting;
use App\Models\JobView;
use App\Models\User;
use App\Services\JobMatches;
use App\Support\CandidateTimeline;
use App\Support\JobPostingChecks;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The candidate's home, a job tracker first (Indeed's "My jobs"): what
 * needs them, where each application stands, what changed lately, then
 * the jobs worth a look.
 */
class CandidateDashboardController extends Controller
{
    /**
     * Jobs are recommended from this match up. Below it most of what a
     * posting asks for is missing, and a list of weak fits teaches the
     * candidate that "recommended" means little.
     */
    public const RECOMMEND_FROM_PERCENT = 50;

    private const RECENTLY_VIEWED = 4;

    /**
     * The parts of a profile a company looks at first, after LinkedIn's
     * profile-strength signals. Links, cover photo and the contact
     * details for CVs are not here: a GitHub link means nothing for an
     * accountant, and companies never see the cover or the contact
     * details.
     */
    private const PROFILE_GAPS = [
        'photo' => 'a photo',
        'headline' => 'a headline',
        'about' => 'About',
        'experience' => 'your experience',
        'skills' => 'your skills',
    ];

    public function index(Request $request, JobMatches $jobMatches): View
    {
        $user = $request->user();
        $profile = $user->candidateProfile;

        $applications = $profile->applications()
            ->with(['jobPosting.company', 'events' => fn ($query) => $query->reorder()->orderBy('created_at')->orderBy('id')])
            ->latest('created_at')
            ->latest('id')
            ->get();

        $statusCounts = collect(CandidateApplicationStatus::cases())
            ->mapWithKeys(fn (CandidateApplicationStatus $status) => [$status->value => 0])
            ->merge($applications->countBy(fn (Application $application) => CandidateApplicationStatus::of($application)->value))
            ->all();

        $changes = $this->changes($applications, $user);

        $recommended = $jobMatches->for($profile)
            ->filter(fn (array $match) => $match['score'] >= self::RECOMMEND_FROM_PERCENT)
            ->values();

        $closingSaved = $this->closingSavedJobs($user, $applications);

        $shownIds = $recommended->pluck('jobPosting.id')->merge($closingSaved->pluck('id'));

        $recentlyViewed = JobView::query()
            ->with(['jobPosting.company', 'jobPosting.skills:id,name'])
            ->where('user_id', $user->id)
            ->whereNotIn('job_posting_id', $shownIds)
            // Only what is still public: a posting taken down or hidden
            // since it was viewed must not be shown here when its public
            // page answers 404.
            ->whereHas('jobPosting', fn ($query) => $query->active())
            ->latest('viewed_at')
            ->latest('id')
            ->take(self::RECENTLY_VIEWED)
            ->get()
            ->pluck('jobPosting');

        $hasSkills = $profile->skills()->exists();
        $importableCv = $hasSkills ? null : $profile->importableCv();

        return view('candidate.dashboard', [
            'user' => $user,
            'stateLine' => $this->stateLine($changes, $statusCounts),
            'statusCounts' => $statusCounts,
            'recentChanges' => $changes->take(3),
            'closingSaved' => $closingSaved,
            'profileGaps' => $this->profileGaps($user, $profile, $importableCv !== null),
            'importableCv' => $importableCv,
            'reviewable' => $this->reviewable($user, $applications),
            'hasSkills' => $hasSkills,
            'recommended' => $recommended,
            'recentlyViewed' => $recentlyViewed,
            'recentlyViewedScores' => $jobMatches->scoresFor($profile, $recentlyViewed),
            'savedCount' => $user->savedJobs()->active()->count(),
            'alertCount' => $user->jobAlerts()->where('is_active', true)->count(),
        ]);
    }

    /**
     * Every change the candidate may see, newest first: their own
     * applications and what happened to them since.
     *
     * @param  Collection<int, Application>  $applications
     * @return Collection<int, array{line: string, at: CarbonInterface, application: Application, byCompany: bool}>
     */
    private function changes(Collection $applications, User $user): Collection
    {
        return $applications
            ->flatMap(fn (Application $application) => collect([[
                'line' => __('You applied to :job at :company', [
                    'job' => $application->jobPosting->title,
                    'company' => $application->jobPosting->company->name,
                ]),
                'at' => $application->created_at,
                'application' => $application,
                'byCompany' => false,
            ]])->concat($application->eventsForCandidate()->map(fn ($event) => [
                'line' => CandidateTimeline::line($event, $application, $user->id),
                'at' => $event->created_at,
                'application' => $application,
                'byCompany' => $event->changed_by_id !== $user->id,
            ])))
            ->sortByDesc(fn (array $change) => $change['at']->getTimestamp())
            ->values();
    }

    /**
     * One line under the greeting that says what moved, counted from the
     * company's changes of the last seven days.
     */
    private function stateLine(Collection $changes, array $statusCounts): ?string
    {
        $moved = $changes
            ->filter(fn (array $change) => $change['byCompany'] && $change['at']->gt(now()->subDays(7)))
            ->pluck('application.id')
            ->unique()
            ->count();

        if ($moved > 0) {
            return trans_choice('One of your applications moved this week.|:count of your applications moved this week.', $moved);
        }

        $open = $statusCounts[CandidateApplicationStatus::Applied->value]
            + $statusCounts[CandidateApplicationStatus::InReview->value]
            + $statusCounts[CandidateApplicationStatus::Offer->value];

        return $open > 0 ? __('No news from the companies this week.') : null;
    }

    /**
     * Saved jobs that close within the same few days the job page warns
     * about, not yet applied to: the one thing on the list that cannot
     * wait. A closing date is the end of that day in the company's zone,
     * already stored as that moment.
     *
     * @param  Collection<int, Application>  $applications
     * @return Collection<int, JobPosting>
     */
    private function closingSavedJobs(User $user, Collection $applications): Collection
    {
        return $user->savedJobs()
            ->active()
            ->where('job_postings.expires_at', '<=', now()->addDays(JobPostingChecks::EXPIRING_WITHIN_DAYS))
            ->whereNotIn('job_postings.id', $applications->pluck('job_posting_id'))
            ->with('company')
            ->orderBy('job_postings.expires_at')
            ->get();
    }

    /**
     * The keys of PROFILE_GAPS still empty. When the CV in the library
     * can fill experience and skills, the import is offered instead, so
     * those two are not asked for twice.
     *
     * @return list<string>
     */
    private function profileGaps(User $user, CandidateProfile $profile, bool $importOffered): array
    {
        $filled = [
            'photo' => filled($user->avatar),
            'headline' => filled($profile->headline),
            'about' => filled($profile->bio),
            'experience' => $profile->experienceRecords()->exists(),
            'skills' => $profile->skills()->exists(),
        ];

        return collect($filled)
            ->reject(fn (bool $isFilled) => $isFilled)
            ->keys()
            ->reject(fn (string $gap) => $importOffered && in_array($gap, ['experience', 'skills'], true))
            ->values()
            ->all();
    }

    /**
     * For each company applied to, the newest application that lets the
     * candidate review its hiring process now, when there is no review
     * yet -- the same rule the review form itself is behind.
     *
     * @param  Collection<int, Application>  $applications
     * @return Collection<int, Application>
     */
    private function reviewable(User $user, Collection $applications): Collection
    {
        return $applications
            ->groupBy(fn (Application $application) => $application->jobPosting->company_id)
            ->map(fn (Collection $ofCompany) => $ofCompany->first(
                fn (Application $application) => $user->can('create', [CompanyReview::class, $application]),
            ))
            ->filter()
            ->values();
    }

    /**
     * How a gap key reads in a sentence.
     */
    public static function gapLabel(string $gap): string
    {
        return __(self::PROFILE_GAPS[$gap]);
    }
}
