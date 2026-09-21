<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStage;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployerDashboardController extends Controller
{
    /**
     * The landing page of a company workspace. Open to every active
     * member, not just owners and managers -- everyone on a hiring team
     * needs somewhere to start.
     */
    public function index(Company $company): View
    {
        // "Waiting on you" is just applications still sitting at the first
        // stage, so it needs no column of its own: an application that has
        // been looked at has been moved.
        $jobPostings = $company->jobPostings()
            ->withCount([
                'applications',
                'applications as new_applications_count' => fn ($query) => $query->where('stage', ApplicationStage::New),
                'reports as open_reporters_count' => fn ($query) => $query
                    ->where('review_status', ReportStatus::Pending)
                    ->select(DB::raw('count(distinct reporter_id)')),
            ])
            ->latest()
            ->latest('id')
            ->get();

        return view('employer.dashboard', [
            'company' => $company,
            'jobPostings' => $jobPostings,
            // Live means candidates can find it: open, approved, and not
            // held back by reports. Counting everything marked active
            // included postings still in review or sent back.
            'openCount' => $jobPostings->filter(fn (JobPosting $jobPosting) => $jobPosting->isOpen()
                && $jobPosting->moderation_status === ModerationStatus::Approved
                && $jobPosting->open_reporters_count < Report::HIDE_AFTER_REPORTERS)->count(),
            'applicationCount' => $jobPostings->sum('applications_count'),
            'newApplicationCount' => $jobPostings->sum('new_applications_count'),
        ]);
    }
}
