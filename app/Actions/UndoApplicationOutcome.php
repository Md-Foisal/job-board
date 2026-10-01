<?php

namespace App\Actions;

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Takes back a hire or rejection made by mistake, while the candidate
 * has not yet been told. The application is open again exactly as it was,
 * and the undo is written into the history like any other change, so the
 * team can see what happened. The queued email notices the decision is
 * gone and is not sent.
 *
 * Returns null when there is nothing left to undo: the window has passed
 * and the decision is final.
 */
class UndoApplicationOutcome
{
    public function __invoke(Application $application, User $undoneBy): ?ApplicationEvent
    {
        return DB::transaction(function () use ($application, $undoneBy) {
            $current = Application::query()->lockForUpdate()->find($application->id);

            if (! $current->decisionIsUndoable()) {
                return null;
            }

            $from = $current->outcome_status;

            $application->outcome_status = ApplicationOutcomeStatus::Active;
            $application->decided_at = null;
            $application->save();

            return $application->events()->create([
                'changed_by_id' => $undoneBy->id,
                'from_outcome_status' => $from->value,
                'to_outcome_status' => ApplicationOutcomeStatus::Active->value,
            ]);
        });
    }
}
