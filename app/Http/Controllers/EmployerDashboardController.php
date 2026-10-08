<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\InvitationStatus;
use App\Enums\PostingState;
use App\Enums\ReportStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Support\JobPostingChecks;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployerDashboardController extends Controller
{
    /**
     * How many jobs with new applications the list names one by one;
     * the rest are counted in a line of their own.
     */
    public const NEW_APPLICATION_ROWS = 5;

    /**
     * The landing page of a company workspace, for every active member:
     * what is waiting on them, and where each open job stands. A plain
     * member sees only what they can act on -- applications to look at --
     * rather than posting, decision, team and verification work shown
     * to them and then refused (Lever, Ashby and Greenhouse filter their
     * home pages by permission the same way).
     */
    public function index(Company $company): View
    {
        $user = auth()->user();
        $canManage = $user->canManage($company);

        $jobPostings = $this->jobPostings($company);
        $openJobs = $jobPostings->filter(fn (JobPosting $jobPosting) => $jobPosting->isOpen())->values();

        return view('employer.dashboard', [
            'company' => $company,
            'canManage' => $canManage,
            'hasPostings' => $jobPostings->isNotEmpty(),
            'openJobs' => $openJobs,
            'stages' => ApplicationStage::cases(),
            'newApplications' => $jobPostings
                ->filter(fn (JobPosting $jobPosting) => $jobPosting->stage_new_count > 0)
                ->sortBy(fn (JobPosting $jobPosting) => $jobPosting->oldest_new_at)
                ->values(),
            'undoable' => $canManage ? $this->undoableDecisions($company) : new Collection,
            'needsChanges' => $canManage
                ? $jobPostings->filter(fn (JobPosting $jobPosting) => PostingState::of($jobPosting) === PostingState::NeedsChanges)->values()
                : new Collection,
            'draftCount' => $canManage
                ? $jobPostings->filter(fn (JobPosting $jobPosting) => PostingState::of($jobPosting) === PostingState::Draft)->count()
                : 0,
            'closingSoon' => $canManage
                ? $openJobs->filter(fn (JobPosting $jobPosting) => $jobPosting->expires_at->lte(now()->addDays(JobPostingChecks::EXPIRING_WITHIN_DAYS)))->values()
                : new Collection,
            'pendingInvitationCount' => $canManage
                ? $company->invitations()->where('status', InvitationStatus::Pending)->where('expires_at', '>', now())->count()
                : 0,
            'documentsRequest' => $canManage ? $company->outstandingDocumentsRequest() : null,
            // Counted for the people who can answer. The company hears of a
            // new review here, by count and not by mail: a mail would date
            // it to the day, which the public page deliberately does not.
            'reviewsAwaitingResponse' => $canManage
                ? $company->reviews()->published()->awaitingResponse()->count()
                : 0,
        ]);
    }

    /**
     * Every posting of the company with its open applications counted by
     * stage, in one query. Only open applications count: one that was
     * turned down or withdrawn is not waiting on anyone, whatever stage it
     * stopped at.
     *
     * @return Collection<int, JobPosting>
     */
    private function jobPostings(Company $company): Collection
    {
        $open = fn (ApplicationStage $stage) => fn ($query) => $query
            ->where('stage', $stage)
            ->where('outcome_status', ApplicationOutcomeStatus::Active);

        $counts = collect(ApplicationStage::cases())
            ->mapWithKeys(fn (ApplicationStage $stage) => ['applications as stage_'.$stage->value.'_count' => $open($stage)])
            ->all();

        return $company->jobPostings()
            ->withCount([
                ...$counts,
                'reports as open_reporters_count' => fn ($query) => $query
                    ->where('review_status', ReportStatus::Pending)
                    ->select(DB::raw('count(distinct reporter_id)')),
            ])
            ->withMin(['applications as oldest_new_at' => $open(ApplicationStage::New)], 'created_at')
            ->with('latestRejection')
            ->latest('published_at')
            ->latest('id')
            ->get()
            ->each(function (JobPosting $jobPosting) use ($company) {
                $jobPosting->setRelation('company', $company);
                // An aggregate comes back as the database's own text, so it
                // is read as the UTC moment every timestamp is stored in.
                $jobPosting->oldest_new_at = $jobPosting->oldest_new_at
                    ? CarbonImmutable::parse($jobPosting->oldest_new_at, 'UTC')
                    : null;
            });
    }

    /**
     * Hires and rejections whose candidate has not been told yet, while
     * they can still be taken back.
     *
     * @return Collection<int, Application>
     */
    private function undoableDecisions(Company $company): Collection
    {
        return Application::query()
            ->whereIn('job_posting_id', $company->jobPostings()->select('id'))
            ->whereIn('outcome_status', [ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected])
            ->where('decided_at', '>', now()->subMinutes(Application::UNDO_MINUTES))
            ->with(['candidateProfile.user:id,name', 'jobPosting:id,title'])
            ->oldest('decided_at')
            ->get();
    }
}
