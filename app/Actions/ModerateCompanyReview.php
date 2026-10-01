<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewRejectionReason;
use App\Models\CompanyReview;
use App\Models\ModerationEvent;
use App\Models\User;
use App\Notifications\CompanyReviewApproved;
use App\Notifications\CompanyReviewPublished;
use App\Notifications\CompanyReviewRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Staff decisions on a company review, each one written to the trail.
 *
 * Reviews are never deleted here. Rejecting takes a review off the
 * public page and keeps both the review and the reason it was held back,
 * so a decision can be checked later and reversed. Pending reports close
 * with the decision, the way they do for job postings: approving judges
 * that they did not hold, rejecting is what they asked for.
 */
class ModerateCompanyReview
{
    /**
     * published_at is the month readers see. It is set when the current
     * text first goes public and left alone when staff only reverse a
     * mistaken rejection; an edit clears it, so the rewritten text is
     * dated by its own approval.
     */
    public function approve(CompanyReview $review, User $staff): ModerationEvent
    {
        $event = DB::transaction(function () use ($review, $staff) {
            $review->moderation_status = ModerationStatus::Approved;
            $review->published_at ??= now();
            $review->save();

            $review->reports()
                ->where('review_status', ReportStatus::Pending->value)
                ->update(['review_status' => ReportStatus::Reviewed->value]);

            return $review->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::ApproveCompanyReview,
            ]);
        });

        // After the commit, never inside it: a mail cannot be recalled if
        // the decision rolls back.
        $review->candidateProfile->user->notify(new CompanyReviewApproved($review));
        Notification::send($review->company->decisionMakers(), new CompanyReviewPublished($review));

        return $event;
    }

    public function reject(CompanyReview $review, User $staff, ReviewRejectionReason $reason, ?string $note = null): ModerationEvent
    {
        $note = trim((string) $note);
        $text = $reason->forWriter().($note === '' ? '' : "\n\n".$note);

        $event = DB::transaction(function () use ($review, $staff, $text) {
            $review->moderation_status = ModerationStatus::Rejected;
            $review->save();

            $review->reports()
                ->where('review_status', ReportStatus::Pending->value)
                ->update(['review_status' => ReportStatus::Actioned->value]);

            return $review->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::RejectCompanyReview,
                'reason' => $text,
            ]);
        });

        $review->candidateProfile->user->notify(new CompanyReviewRejected($review, $text));

        return $event;
    }
}
