<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\JobPosting;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $jobPostings = JobPosting::with('company:id,name,slug,logo_path')
            ->active()
            ->latest('created_at')
            ->take(12)
            ->get();

        $categories = Category::withCount(['jobPostings' => fn ($query) => $query->active()])
            ->orderByDesc('job_postings_count')
            ->take(8)
            ->get();

        return view('home', [
            'jobPostings' => $jobPostings,
            'categories' => $categories,
        ]);
    }
}
