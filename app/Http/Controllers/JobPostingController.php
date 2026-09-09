<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\JobView;
use Illuminate\View\View;

class JobPostingController extends Controller
{
    public function show(JobPosting $jobPosting): View
    {
        $this->authorize('view', $jobPosting);

        $jobPosting->load([
            'company:id,name,slug,logo_path,verified_at',
            'skills:id,name',
            'postedBy:id,name,avatar',
            'screeningQuestions',
        ]);

        $this->recordView($jobPosting);

        return view('jobs.show', ['jobPosting' => $jobPosting]);
    }

    /**
     * Recently-viewed side effect for the candidate dashboard -- one row
     * per (user, job), upserted so repeat visits just bump viewed_at
     * rather than piling up rows. Guests write nothing (no User row to
     * attach to).
     */
    private function recordView(JobPosting $jobPosting): void
    {
        $user = auth()->user();

        if (!$user || !$user->isCandidate()) {
            return;
        }

        JobView::updateOrCreate(
            ['user_id' => $user->id, 'job_posting_id' => $jobPosting->id],
            ['viewed_at' => now()],
        );
    }
}
