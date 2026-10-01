<?php

namespace App\Http\Controllers;

use App\Actions\RecordJobView;
use App\Models\JobPosting;
use App\Services\EmployerResponsiveness;
use App\Services\JobPostingStructuredData;
use App\Support\ReviewSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobPostingController extends Controller
{
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
            // The recruiter's own face, when they have set one up: the
            // candidate is deciding whether to apply, and a name with a
            // person behind it is part of that decision.
            'postedBy:id,name,avatar',
            'postedBy.recruiterProfile',
            'screeningQuestions',
        ]);

        $recordView($jobPosting, $request);

        return view('jobs.show', [
            'jobPosting' => $jobPosting,
            'reviewSummary' => ReviewSummary::of($jobPosting->company),
            'responsivePercent' => $responsiveness->percentFor($jobPosting->company),
            // Only a publicly visible posting carries JSON-LD. A company
            // member previewing their own draft sees the same page, but
            // must not emit markup telling Google the job is live.
            'structuredData' => $jobPosting->isPubliclyVisible()
                ? $structuredData->toJson($jobPosting)
                : null,
        ]);
    }
}
