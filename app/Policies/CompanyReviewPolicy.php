<?php

namespace App\Policies;

use App\Enums\ModerationStatus;
use App\Models\Application;
use App\Models\CompanyReview;
use App\Models\User;
use App\Support\ReviewEligibility;

class CompanyReviewPolicy
{
    /**
     * Only about one's own application, and only once it gives the
     * writer something real to describe.
     */
    public function create(User $user, Application $application): bool
    {
        return $user->candidateProfile !== null
            && $user->candidateProfile->id === $application->candidate_profile_id
            && ReviewEligibility::of($application)->allows();
    }

    /**
     * The writer may revise their review, unless they have since joined
     * the company: from then on a rewrite would be an insider's review
     * wearing an applicant's badge. The review they wrote before stays.
     */
    public function update(User $user, CompanyReview $review): bool
    {
        return $review->isWrittenBy($user)
            && ! $user->hasWorkedAt($review->company);
    }

    /**
     * Only the writer can take a review down. The company never can --
     * it can answer or report, but not silence. Staff do not delete
     * either: they reject, which takes the review out of public view and
     * leaves the decision and its reason on the record. The other way a
     * review disappears is its writer's account being erased.
     */
    public function delete(User $user, CompanyReview $review): bool
    {
        return $review->isWrittenBy($user);
    }

    /**
     * The company's one public answer comes from the people who speak
     * for it, and only to a review that is actually public.
     */
    public function respond(User $user, CompanyReview $review): bool
    {
        return $review->moderation_status === ModerationStatus::Approved
            && $user->canManage($review->company);
    }
}
