<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\CandidateApplicationStatus;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CandidateApplicationController extends Controller
{
    /**
     * Two tabs, as job trackers split them (Indeed's My jobs): Active --
     * everything still open, the default -- and Closed. The dashboard's
     * counts link here with ?status=, which narrows the Active tab to one
     * step, or opens Closed; an unknown value is ignored.
     */
    public function index(Request $request): View
    {
        $profileId = $request->user()->candidateProfile->id;
        $status = CandidateApplicationStatus::tryFrom((string) $request->query('status'));
        $closed = $status === CandidateApplicationStatus::Closed;

        $applications = Application::query()
            ->where('candidate_profile_id', $profileId)
            ->when(
                $status,
                fn ($query) => $status->scope($query),
                fn ($query) => CandidateApplicationStatus::whereOpen($query),
            )
            ->with('jobPosting.company')
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('candidate.applications.index', [
            'applications' => $applications,
            'status' => $status,
            'closed' => $closed,
            // An empty tab says something different to someone who has
            // never applied than to someone whose applications are all in
            // the other tab.
            'hasAny' => $applications->isNotEmpty()
                || Application::query()->where('candidate_profile_id', $profileId)->exists(),
        ]);
    }

    /**
     * The candidate's own application timeline --
     * this is the ghosting-killer: every stage and outcome change the
     * employer made is visible here, in order, instead of the silence
     * that 61% of candidates report.
     *
     * changedBy is deliberately NOT eager-loaded. It is settled that the candidate never sees which individual staff
     * member touched their application, only the company -- not
     * loading the relation at all is a stronger guarantee than
     * remembering not to print it.
     */
    public function show(Application $application): View
    {
        $this->authorize('view', $application);

        $application->load([
            'jobPosting.company',
            'resumeDocument',
            'screeningAnswers.screeningQuestion',
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
     * The CV exactly as it went with this application, for the candidate
     * who sent it -- even after they took it out of their library, since
     * the application keeps its own copy. Opened in the browser when it is
     * a kind the browser draws (a PDF), downloaded otherwise.
     */
    public function resume(Application $application)
    {
        $this->authorize('view', $application);

        $document = $application->resumeDocument;

        abort_if($document === null, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        if (! DocumentPreviewController::canPreview($document)) {
            return Storage::disk('local')->download($document->file_path, $document->original_filename);
        }

        return Storage::disk('local')->response($document->file_path, $document->original_filename, [
            'Content-Type' => DocumentPreviewController::contentType($document),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
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
