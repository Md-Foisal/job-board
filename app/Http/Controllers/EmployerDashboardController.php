<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStage;
use App\Enums\AvailabilityStatus;
use App\Models\Company;
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
            ])
            ->latest()
            ->get();

        return view('employer.dashboard', [
            'company' => $company,
            'jobPostings' => $jobPostings,
            'openCount' => $jobPostings->where('availability_status', AvailabilityStatus::Active)->count(),
            'applicationCount' => $jobPostings->sum('applications_count'),
            'newApplicationCount' => $jobPostings->sum('new_applications_count'),
        ]);
    }
}
