<?php

namespace App\Actions;

use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\User;
use App\Notifications\ApplicationStageChanged;
use Illuminate\Support\Facades\DB;

/**
 * Moving an application forward and recording that it moved are one act,
 * never two.
 *
 * This is the whole ghosting-killer mechanic and it works precisely
 * because it asks nothing extra of the employer: the stage change is
 * something they do for their own sake -- to keep track, to answer to
 * whoever asked -- and the candidate's answer falls out of it for free.
 * Let the two come apart and the candidate is back to silence.
 */
class ChangeApplicationStage
{
    public function __invoke(Application $application, User $changedBy, ApplicationStage $to): ?ApplicationEvent
    {
        $from = $application->stage;

        if ($from === $to) {
            return null;
        }

        $event = DB::transaction(function () use ($application, $changedBy, $from, $to) {
            $application->stage = $to;
            $application->save();

            // Stored as scalars: this table carries no casts, and every
            // other writer of it (the candidate's own withdrawal) writes
            // ->value too. One table, one shape.
            return $application->events()->create([
                'changed_by_id' => $changedBy->id,
                'from_stage' => $from->value,
                'to_stage' => $to->value,
            ]);
        });

        // Sent after the transaction commits, never inside it: a mail that
        // goes out for a change that then rolls back cannot be recalled.
        // One known recipient, so it goes directly rather than through an
        // event -- there is no fan-out here to work out.
        // Nobody is written to once they have deleted their account: they
        // asked to leave, and an erased one's address cannot receive mail.
        $candidate = $application->candidateProfile->user;

        if (! $candidate->trashed()) {
            $candidate->notify(new ApplicationStageChanged($application));
        }

        return $event;
    }
}
