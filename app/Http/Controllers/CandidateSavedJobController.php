<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class CandidateSavedJobController extends Controller
{
    public function index(Request $request): View
    {
        // saved_jobs is a bare pivot (no id, no timestamps -- see its
        // migration), so there is no SavedJob model to query directly.
        // User::savedJobs() is the established belongsToMany for it.
        $jobPostings = $request->user()->savedJobs()
            ->with('company')
            ->latest('job_postings.created_at')
            ->get();

        return view('candidate.saved-jobs.index', [
            'jobPostings' => $jobPostings,
        ]);
    }
}
