<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Unsaving, in one go, the saved jobs that are no longer open. They
     * are hidden rather than unsaved automatically, because one hidden
     * while reports are reviewed can come back -- but most are expired or
     * closed for good, and the candidate cannot see them to unsave one by
     * one, so without this the "no longer available" count only grows.
     */
    public function pruneUnavailable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $open = JobPosting::query()->active()->select('job_postings.id');
        $unavailable = $user->savedJobs()->whereNotIn('job_postings.id', $open)->pluck('job_postings.id');

        $user->savedJobs()->detach($unavailable);

        return back()->with('success', trans_choice('{0} Nothing to remove.|{1} Removed one saved job that is no longer open.|[2,*] Removed :count saved jobs that are no longer open.', $unavailable->count()));
    }
}
