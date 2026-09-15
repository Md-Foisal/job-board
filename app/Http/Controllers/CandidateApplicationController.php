<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * The candidate's own application timeline (claude/14 route ২০) --
     * this is the ghosting-killer: every stage and outcome change the
     * employer made is visible here, in order, instead of the silence
     * that 61% of candidates report.
     *
     * changedBy is deliberately NOT eager-loaded. claude/14 step 6-খ
     * settled that the candidate never sees which individual staff
     * member touched their application, only the company -- not
     * loading the relation at all is a stronger guarantee than
     * remembering not to print it.
     */
    public function show(Application $application): View
    {
        $this->authorize('view', $application);

        $application->load([
            'jobPosting.company',
            // Oldest first: this reads top-to-bottom as a history. The
            // model's events() relation defaults to newest-first for
            // list contexts, so reorder() overrides it here.
            'events' => fn ($query) => $query->reorder('created_at'),
        ]);

        return view('candidate.applications.show', [
            'application' => $application,
        ]);
    }

    /**
     * Withdrawing is the candidate's own side of the outcome axis. It
     * writes an ApplicationEvent like any other transition -- without
     * that row the candidate's own action would be the one thing
     * missing from their own history.
     */
    public function withdraw(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('withdraw', $application);

        DB::transaction(function () use ($request, $application) {
            $from = $application->outcome_status;

            $application->update([
                'outcome_status' => ApplicationOutcomeStatus::Withdrawn,
            ]);

            $application->events()->create([
                'changed_by_id' => $request->user()->id,
                'from_outcome_status' => $from->value,
                'to_outcome_status' => ApplicationOutcomeStatus::Withdrawn->value,
            ]);
        });

        return redirect()
            ->route('candidate.applications.show', $application)
            ->with('success', __('Application withdrawn.'));
    }
}
