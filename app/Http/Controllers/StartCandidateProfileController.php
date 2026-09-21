<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Starting the candidate side after registering for the other one -- the
 * same empty profile registration makes, created on request instead.
 * Being a candidate is derived from this row existing (claude/13, 4-c),
 * so creating it is the whole act.
 */
class StartCandidateProfileController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isCandidate()) {
            return redirect()->route('candidate.dashboard');
        }

        $user->candidateProfile()->create([]);

        return redirect()
            ->route('candidate.profile.edit')
            ->with('success', __('Your candidate profile is ready. Fill it in so employers can see who you are.'));
    }
}
