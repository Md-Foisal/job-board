<?php

namespace App\Http\Controllers;

use App\Models\CvVersion;
use App\Models\JobPosting;
use App\Support\CvVersions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * "Tailor my CV for this job": opens the candidate's CV made for this job
 * on the board, making it the first time as a copy of the CV they worked
 * on last. Open to a job anyone can see, or one the candidate already
 * applied to.
 */
class TailorCvController extends Controller
{
    public function __invoke(JobPosting $jobPosting): RedirectResponse
    {
        $profile = auth()->user()->candidateProfile;

        abort_unless(
            Gate::allows('view', $jobPosting) || $profile->applications()->where('job_posting_id', $jobPosting->id)->exists(),
            404,
        );

        $version = CvVersions::forJob($profile, $jobPosting);

        if ($version === null) {
            return redirect()->route('candidate.cv-builder')->with('status', __('You can keep :count CVs. Delete one you no longer use to make one for this job.', ['count' => CvVersion::MAX_PER_PROFILE]));
        }

        return redirect()->route('candidate.cv-builder', ['version' => $version->id]);
    }
}
