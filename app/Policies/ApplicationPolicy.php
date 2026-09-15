<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Whether the user can apply to this job posting: must be a
     * candidate, the posting must actually be open to applicants, they
     * must not work at the posting company (self-apply block --
     * conflict-of-interest), and they must not have already applied.
     */
    public function create(User $user, JobPosting $jobPosting): bool
    {
        if (!$user->isCandidate()) {
            return false;
        }

        if (!$jobPosting->isPubliclyVisible()) {
            return false;
        }

        if ($user->worksAt($jobPosting->company)) {
            return false;
        }

        return !Application::query()
            ->where('job_posting_id', $jobPosting->id)
            ->where('candidate_profile_id', $user->candidateProfile->id)
            ->exists();
    }

    /**
     * Whether the user can view this application -- only the candidate
     * who filed it (the employer side of this check is a separate
     * Membership-based ability, added when the employer-side pages are
     * built).
     */
    public function view(User $user, Application $application): bool
    {
        return $user->candidateProfile?->id === $application->candidate_profile_id;
    }

    /**
     * Withdraw is only meaningful while the application is still active.
     */
    public function withdraw(User $user, Application $application): bool
    {
        return $this->view($user, $application)
            && $application->outcome_status === \App\Enums\ApplicationOutcomeStatus::Active;
    }
}
