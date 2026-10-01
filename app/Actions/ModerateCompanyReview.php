<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Enums\ResponseRejectionReason;
use App\Enums\ReviewRejectionReason;
use App\Models\CompanyReview;
use App\Models\ModerationEvent;
use App\Models\User;
use App\Notifications\CompanyReviewApproved;
use App\Notifications\CompanyReviewRejected;
use App\Notifications\CompanyReviewResponsePublished;
use App\Notifications\ReviewResponseRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Staff decisions on a company review and on the company's answer to it,
 * each one written to the trail. The two are decided separately: a fair
 * review can draw an answer that names its writer, and the other way
 * round.
 *
 * No decision touches updated_at, which stays the time the writer last
 * changed the review: the queue sorts and ages reviews by it, and the
 * panel shows it as when the review was written.
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
            CompanyReview::withoutTimestamps(fn () => $review->save());

            $review->reports()
                ->where('review_status', ReportStatus::Pending->value)
                ->update(['review_status' => ReportStatus::Reviewed->value]);

            return $review->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::ApproveCompanyReview,
            ]);
        });

        // After the commit, never inside it: a mail cannot be recalled if
        // the decision rolls back. The company is not mailed: a mail on the
        // day a review goes up would date it to the day, which the public
        // page deliberately does not, and the company knows when it turned
        // whom down.
        $review->candidateProfile->user->notify(new CompanyReviewApproved($review));

        return $event;
    }

    public function reject(CompanyReview $review, User $staff, ReviewRejectionReason $reason, ?string $note = null): ModerationEvent
    {
        $note = trim((string) $note);
        $text = $reason->forWriter().($note === '' ? '' : "\n\n".$note);

        $event = DB::transaction(function () use ($review, $staff, $text) {
            $review->moderation_status = ModerationStatus::Rejected;
            CompanyReview::withoutTimestamps(fn () => $review->save());

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

    /**
     * The writer hears that the company answered: it is their review, and
     * the answer may speak to them directly.
     */
    public function approveResponse(CompanyReview $review, User $staff): ModerationEvent
    {
        $event = DB::transaction(function () use ($review, $staff) {
            $review->response_status = ModerationStatus::Approved;
            CompanyReview::withoutTimestamps(fn () => $review->save());

            return $review->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::ApproveReviewResponse,
            ]);
        });

        $review->candidateProfile->user->notify(new CompanyReviewResponsePublished($review));

        return $event;
    }

    /**
     * The company is told why, so the people who can answer for it can
     * write the answer again.
     */
    public function rejectResponse(CompanyReview $review, User $staff, ResponseRejectionReason $reason, ?string $note = null): ModerationEvent
    {
        $note = trim((string) $note);
        $text = $reason->forCompany().($note === '' ? '' : "\n\n".$note);

        $event = DB::transaction(function () use ($review, $staff, $text) {
            $review->response_status = ModerationStatus::Rejected;
            CompanyReview::withoutTimestamps(fn () => $review->save());

            return $review->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::RejectReviewResponse,
                'reason' => $text,
            ]);
        });

        Notification::send($review->company->decisionMakers(), new ReviewResponseRejected($review, $text));

        return $event;
    }
}
