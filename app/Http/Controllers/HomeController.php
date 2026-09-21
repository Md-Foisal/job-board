<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The homepage is a teaser, not the full listing -- it shows a small,
     * recent sample and sends the visitor to the search page (route
     * jobs.index) for everything else.
     */
    private const RECENT_OPENINGS_COUNT = 6;

    public function index(): View
    {
        $jobPostings = JobPosting::with('company:id,name,slug,logo_path,verified_at')
            ->active()
            ->latest('created_at')
            ->take(self::RECENT_OPENINGS_COUNT)
            ->get();

        $categories = Category::withCount(['jobPostings' => fn ($query) => $query->active()])
            ->orderByDesc('job_postings_count')
            ->take(8)
            ->get();

        return view('home', [
            'jobPostings' => $jobPostings,
            'categories' => $categories,
            'openJobsCount' => JobPosting::active()->count(),
            'hiringCompaniesCount' => Company::query()
                ->where('account_status', AccountStatus::Active)
                ->whereHas('jobPostings', fn ($query) => $query->active())
                ->count(),
        ]);
    }
}
