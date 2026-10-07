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
        // saved_jobs is a pivot without an id, so there is no SavedJob
        // model to query directly; User::savedJobs() is the belongsToMany
        // for it, and its created_at is when the job was saved.
        // Saved jobs that stopped being public are left out rather than
        // unsaved: one hidden while reports are reviewed comes back here by
        // itself if staff clear it. Showing them meanwhile would show what
        // the public page no longer does -- including an edit nobody has
        // reviewed yet.
        // Latest saved first: the job someone saved this morning is the
        // one they came back for. Rows saved before the time was kept go
        // last on every database, not only on those that sort an empty
        // value lowest.
        $jobPostings = $request->user()->savedJobs()
            ->active()
            ->with('company')
            ->orderByRaw('saved_jobs.created_at is null')
            ->latest('saved_jobs.created_at')
            ->latest('job_postings.id')
            ->get();

        return view('candidate.saved-jobs.index', [
            'jobPostings' => $jobPostings,
            'unavailableCount' => $request->user()->savedJobs()->count() - $jobPostings->count(),
            // A saved job already applied to says so on its card, as
            // LinkedIn's saved list marks it, so the list never invites a
            // second application it would refuse.
            'appliedIds' => $request->user()->candidateProfile->applications()
                ->whereIn('job_posting_id', $jobPostings->modelKeys())
                ->pluck('job_posting_id')
                ->all(),
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
