<?php

namespace App\Http\Controllers;

use App\Actions\RecordJobView;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\EmployerResponsiveness;
use App\Services\JobPostingStructuredData;
use App\Support\ReviewSummary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobPostingController extends Controller
{
    /**
     * How many other openings the page suggests under the description.
     */
    private const SIMILAR_COUNT = 3;

    public function show(
        Request $request,
        JobPosting $jobPosting,
        JobPostingStructuredData $structuredData,
        RecordJobView $recordView,
        EmployerResponsiveness $responsiveness,
    ): View {
        $this->authorize('view', $jobPosting);

        $jobPosting->load([
            // The whole company row, not a column subset. A subset used
            // to be listed here and it silently broke two things: the
            // isPubliclyVisible() check below reads company.account_status
            // (a column the subset omitted, which Eloquent then reads as
            // null, so the check always failed), and the JSON-LD's
            // hiringOrganization.sameAs reads company.website_url (also
            // omitted, so it vanished from the markup). A companies row
            // is a dozen small columns -- the subset saved nothing and
            // cost both of those.
            'company',
            'skills:id,name',
            'categories:id,name,slug',
            // The recruiter's own face, when they have set one up: the
            // candidate is deciding whether to apply, and a name with a
            // person behind it is part of that decision.
            'postedBy:id,name,avatar',
            'postedBy.recruiterProfile',
            'screeningQuestions',
        ]);

        $recordView($jobPosting, $request);

        $user = $request->user();
        $isCandidate = $user?->isCandidate() ?? false;
        $isMember = $user !== null && $user->worksAt($jobPosting->company);
        $isPublic = $jobPosting->isPubliclyVisible();
        $application = $isCandidate ? $this->applicationOf($user, $jobPosting) : null;
        $similarJobs = $isPublic ? $this->similarJobs($jobPosting) : new Collection;

        return view('jobs.show', [
            'jobPosting' => $jobPosting,
            'isPublic' => $isPublic,
            'isCandidate' => $isCandidate,
            'isMember' => $isMember,
            'application' => $application,
            // The checks ApplicationPolicy::create() makes,
            // answered from what is already loaded.
            'canApply' => $isCandidate && $isPublic && ! $isMember && $application === null,
            'reviewSummary' => ReviewSummary::of($jobPosting->company),
            'responsivePercent' => $responsiveness->percentFor($jobPosting->company),
            'openJobsCount' => $jobPosting->company->jobPostings()->active()->count(),
            'similarJobs' => $similarJobs,
            'savedJobIds' => $isCandidate ? $user->savedJobIdsAmong($similarJobs) : [],
            // Guests too: the button signs them in and brings them back.
            // Employers and staff have no list of saved jobs.
            'canSave' => $user === null || $isCandidate,
            // Only a publicly visible posting carries JSON-LD. A company
            // member previewing their own draft sees the same page, but
            // must not emit markup telling Google the job is live.
            'structuredData' => $isPublic ? $structuredData->toJson($jobPosting) : null,
        ]);
    }

    /**
     * The candidate's own application to this job, if they have sent
     * one: the page then says so and links to its timeline, instead of
     * offering an Apply button that could only end in "forbidden".
     */
    private function applicationOf(User $user, JobPosting $jobPosting): ?Application
    {
        return Application::query()
            ->where('job_posting_id', $jobPosting->id)
            ->where('candidate_profile_id', $user->candidateProfile->id)
            ->first();
    }

    /**
     * Other open jobs in the same categories, newest first. A posting
     * with no category has nothing to be similar to, so it gets none
     * rather than a list of whatever was posted last.
     *
     * @return Collection<int, JobPosting>
     */
    private function similarJobs(JobPosting $jobPosting): Collection
    {
        $categoryIds = $jobPosting->categories->modelKeys();

        if ($categoryIds === []) {
            return new Collection;
        }

        return JobPosting::query()
            ->with('company:id,name,slug,logo_path,verified_at')
            ->active()
            ->whereKeyNot($jobPosting->id)
            ->whereHas('categories', fn ($query) => $query->whereKey($categoryIds))
            ->latest('published_at')
            ->latest('id')
            ->take(self::SIMILAR_COUNT)
            ->get();
    }
}
