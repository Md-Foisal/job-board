<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\JobView;
use App\Services\JobPostingStructuredData;
use Illuminate\View\View;

class JobPostingController extends Controller
{
    public function show(JobPosting $jobPosting, JobPostingStructuredData $structuredData): View
    {
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
            // The recruiter's own face, when they have set one up: the
            // candidate is deciding whether to apply, and a name with a
            // person behind it is part of that decision.
            'postedBy:id,name,avatar',
            'postedBy.recruiterProfile',
            'screeningQuestions',
        ]);

        $this->recordView($jobPosting);

        return view('jobs.show', [
            'jobPosting' => $jobPosting,
            // Only a publicly visible posting carries JSON-LD. A company
            // member previewing their own draft sees the same page, but
            // must not emit markup telling Google the job is live.
            'structuredData' => $jobPosting->isPubliclyVisible()
                ? $structuredData->toJson($jobPosting)
                : null,
        ]);
    }

    /**
     * Recently-viewed side effect for the candidate dashboard -- one row
     * per (user, job), upserted so repeat visits just bump viewed_at
     * rather than piling up rows. Guests write nothing (no User row to
     * attach to).
     */
    private function recordView(JobPosting $jobPosting): void
    {
        $user = auth()->user();

        if (!$user || !$user->isCandidate()) {
            return;
        }

        JobView::updateOrCreate(
            ['user_id' => $user->id, 'job_posting_id' => $jobPosting->id],
            ['viewed_at' => now()],
        );
    }
}
