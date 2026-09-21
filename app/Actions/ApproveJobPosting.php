<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Models\JobPosting;
use App\Models\ModerationEvent;
use App\Models\User;
use App\Notifications\JobPostingApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Clearing a posting for the public site, and writing down who did it.
 *
 * Any reports still pending against the posting are closed as reviewed:
 * approving it is a judgement that they did not hold. One decision, one
 * event -- the event is about the posting, never about each report.
 */
class ApproveJobPosting
{
    public function __invoke(JobPosting $jobPosting, User $staff): ModerationEvent
    {
        // The queue hides the button for a banned company's posting; this
        // is the second layer, for any other caller.
        if ($jobPosting->company->account_status !== AccountStatus::Active) {
            throw new \DomainException('A posting from a banned company cannot be approved.');
        }

        $event = DB::transaction(function () use ($jobPosting, $staff) {
            $jobPosting->moderation_status = ModerationStatus::Approved;
            $jobPosting->save();

            $jobPosting->reports()
                ->where('review_status', ReportStatus::Pending->value)
                ->update(['review_status' => ReportStatus::Reviewed->value]);

            return $jobPosting->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::ApproveJobPosting,
            ]);
        });

        // After the commit, never inside it: a mail cannot be recalled if
        // the decision rolls back.
        Notification::send($jobPosting->company->decisionMakers(), new JobPostingApproved($jobPosting));

        return $event;
    }
}
