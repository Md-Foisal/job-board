<?php

namespace App\Actions;

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\User;
use App\Notifications\ApplicationOutcomeDecided;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The company's final answer on an application: hired, or not.
 *
 * It is the other half of the ghosting-killer. Stage moves tell a
 * candidate they are still in the running; without this, the only way an
 * application ever ended was the candidate giving up on it. So a decision
 * always reaches the candidate -- there is no quiet rejection.
 *
 * An outcome is final. It is only ever taken from an active application,
 * and once set, the stage stops moving, so a candidate who has been told
 * "no" is never told afterwards that they moved forward.
 */
class ChangeApplicationOutcome
{
    public function __invoke(Application $application, User $decidedBy, ApplicationOutcomeStatus $to): ?ApplicationEvent
    {
        if (! in_array($to, [ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected], true)) {
            throw new InvalidArgumentException('A company can only hire or reject.');
        }

        $event = DB::transaction(function () use ($application, $decidedBy, $to) {
            // Read again under a lock: a candidate withdrawing at the same
            // moment must not end up both withdrawn and rejected.
            $current = Application::query()->lockForUpdate()->find($application->id);

            if ($current->outcome_status !== ApplicationOutcomeStatus::Active) {
                return null;
            }

            $application->outcome_status = $to;
            $application->save();

            return $application->events()->create([
                'changed_by_id' => $decidedBy->id,
                'from_outcome_status' => ApplicationOutcomeStatus::Active->value,
                'to_outcome_status' => $to->value,
            ]);
        });

        if ($event === null) {
            return null;
        }

        $candidate = $application->candidateProfile->user;

        if (! $candidate->trashed()) {
            $candidate->notify(new ApplicationOutcomeDecided($application));
        }

        return $event;
    }
}
