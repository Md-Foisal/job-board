<?php

namespace App\Actions;

use App\Enums\ModerationStatus;
use App\Models\CompanyReview;
use App\Models\User;
use App\Notifications\ReviewResponseAwaitingReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The company's one public answer to a review: writing or revising it,
 * and taking it back.
 *
 * Every saved answer goes to staff before readers see it, for the same
 * reason reviews do -- and because the company knows who it turned down,
 * an answer is the easiest place for a writer to be named. Until staff
 * approve a revision, the review shows no answer at all rather than the
 * old one, so the page never shows words nobody checked.
 *
 * Authorisation is the caller's: the policy's respond ability.
 */
class RespondToCompanyReview
{
    public function __invoke(CompanyReview $review, User $by, string $body): CompanyReview
    {
        $wasPending = DB::transaction(function () use ($review, $by, $body) {
            // Two managers answering at once end with the last answer, not
            // with one half-overwritten by the other.
            $review = CompanyReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $wasPending = $review->response_status === ModerationStatus::Pending;

            $review->forceFill([
                'response_body' => trim($body),
                'response_status' => ModerationStatus::Pending,
                'responded_by_id' => $by->id,
                'responded_at' => now(),
            ]);

            // updated_at stays the time the review's own text last
            // changed: the staff queue sorts and ages reviews by it.
            CompanyReview::withoutTimestamps(fn () => $review->save());

            return $wasPending;
        });

        $review->refresh();

        // An answer already waiting is already in the queue.
        if (! $wasPending) {
            Notification::send(
                User::query()->activeStaff()->get()->reject(fn (User $staff) => $staff->worksAt($review->company) || $review->isWrittenBy($staff)),
                new ReviewResponseAwaitingReview($review),
            );
        }

        return $review;
    }

    /**
     * The company may withdraw its answer at any time; the review stays.
     */
    public function withdraw(CompanyReview $review): CompanyReview
    {
        $review->forceFill([
            'response_body' => null,
            'response_status' => null,
            'responded_by_id' => null,
            'responded_at' => null,
        ]);

        CompanyReview::withoutTimestamps(fn () => $review->save());

        return $review;
    }
}
