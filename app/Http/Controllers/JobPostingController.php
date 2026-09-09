<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use Illuminate\View\View;

class JobPostingController extends Controller
{
    /**
     * Minimal detail view -- enough for the homepage's job cards to link
     * somewhere real. The full spec (recruiter mini-card, apply action,
     * screening questions, share/report, expiry badge) is Task #4.
     */
    public function show(JobPosting $jobPosting): View
    {
        $this->authorize('view', $jobPosting);

        $jobPosting->load('company:id,name,slug,logo_path', 'skills:id,name');

        return view('jobs.show', ['jobPosting' => $jobPosting]);
    }
}
