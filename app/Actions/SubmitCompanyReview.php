<?php

namespace App\Actions;

use App\Enums\ModerationStatus;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\CompanyReview;
use App\Models\User;
use App\Notifications\CompanyReviewAwaitingReview;
use App\Support\ReviewEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Writes a candidate's one review of a company, new or revised, and sends
 * it back to staff: nothing reaches the public page without a person
 * reading it first, and an edit after approval could otherwise slip in
 * anything at all.
 *
 * The review is attached to the candidate's most recent application to
 * the company that qualifies them to write it. A candidate who applied
 * twice and updates their review after the second process is most likely
 * describing that one, so that is the proof staff should see.
 *
 * Authorisation is the caller's: create on the application for a new
 * review, update on the review for a revision.
 */
class SubmitCompanyReview
{
    /**
     * @param  array{overall_rating: int, communication_rating: int, job_as_described: string, title: string, body: string}  $data
     */
    public function __invoke(Application $from, array $data): CompanyReview
    {
        $companyId = $from->jobPosting->company_id;
        $candidateProfileId = $from->candidate_profile_id;

        [$review, $wasPending] = DB::transaction(function () use ($from, $data, $companyId, $candidateProfileId) {
            // Two tabs submitting at once must end as one review, not a
            // unique-key error: the second waits here and then finds the
            // first one's row.
            CandidateProfile::query()->whereKey($candidateProfileId)->lockForUpdate()->first();

            $review = CompanyReview::query()
                ->where('company_id', $companyId)
                ->where('candidate_profile_id', $candidateProfileId)
                ->first() ?? new CompanyReview([
                    'company_id' => $companyId,
                    'candidate_profile_id' => $candidateProfileId,
                ]);

            $wasPending = $review->exists && $review->moderation_status === ModerationStatus::Pending;
            $basis = ReviewEligibility::latestQualifying($candidateProfileId, $companyId);

            $review->fill([
                'application_id' => $basis?->id ?? $review->application_id ?? $from->id,
                'overall_rating' => $data['overall_rating'],
                'communication_rating' => $data['communication_rating'],
                'job_as_described' => $data['job_as_described'],
                'title' => trim($data['title']),
                'body' => trim($data['body']),
                'moderation_status' => ModerationStatus::Pending,
            ])->save();

            return [$review, $wasPending];
        });

        // A review already waiting is already in the queue; telling staff
        // again for every edit would only teach them to ignore the mail.
        if (! $wasPending) {
            Notification::send(
                User::query()->activeStaff()->get()->reject(fn (User $staff) => $staff->worksAt($review->company)),
                new CompanyReviewAwaitingReview($review),
            );
        }

        return $review;
    }
}
