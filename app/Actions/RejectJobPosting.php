<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Models\JobPosting;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Keeping a posting off the public site. A reason is required: the
 * employer is owed an explanation, and whoever reviews the trail later
 * needs to know what the decision rested on.
 *
 * Reports pending against the posting are closed as actioned -- this
 * decision is the action they were asking for.
 */
class RejectJobPosting
{
    public function __invoke(JobPosting $jobPosting, User $staff, string $reason): ModerationEvent
    {
        return DB::transaction(function () use ($jobPosting, $staff, $reason) {
            $jobPosting->moderation_status = ModerationStatus::Rejected;
            $jobPosting->save();

            $jobPosting->reports()
                ->where('review_status', ReportStatus::Pending->value)
                ->update(['review_status' => ReportStatus::Actioned->value]);

            return $jobPosting->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::RejectJobPosting,
                'reason' => $reason,
            ]);
        });
    }
}
