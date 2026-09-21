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
        // Saved jobs that stopped being public are left out rather than
        // unsaved: one hidden while reports are reviewed comes back here by
        // itself if staff clear it. Showing them meanwhile would show what
        // the public page no longer does -- including an edit nobody has
        // reviewed yet.
        $jobPostings = $request->user()->savedJobs()
            ->active()
            ->with('company')
            ->latest('job_postings.created_at')
            ->latest('job_postings.id')
            ->get();

        return view('candidate.saved-jobs.index', [
            'jobPostings' => $jobPostings,
            'unavailableCount' => $request->user()->savedJobs()->count() - $jobPostings->count(),
        ]);
    }
}
