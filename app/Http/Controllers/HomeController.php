<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Support\PublicCache;
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
        // The same for every visitor and the busiest page on the site: four
        // queries, two of them counts across every posting.
        return view('home', PublicCache::remember('home', fn () => [
            'jobPostings' => JobPosting::with('company:id,name,slug,logo_path,verified_at')
                ->active()
                ->latest('created_at')
                ->latest('id')
                ->take(self::RECENT_OPENINGS_COUNT)
                ->get(),
            'categories' => Category::withCount(['jobPostings' => fn ($query) => $query->active()])
                ->orderByDesc('job_postings_count')
                ->take(8)
                ->get(),
            'openJobsCount' => JobPosting::active()->count(),
            'hiringCompaniesCount' => Company::query()
                ->where('account_status', AccountStatus::Active)
                ->whereHas('jobPostings', fn ($query) => $query->active())
                ->count(),
        ]));
    }
}
