<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CandidateApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $applications = Application::query()
            ->where('candidate_profile_id', $request->user()->candidateProfile->id)
            ->with('jobPosting.company')
            ->latest('created_at')
            ->paginate(15);

        return view('candidate.applications.index', [
            'applications' => $applications,
        ]);
    }
}
