<?php

namespace App\Policies;

use App\Enums\ApplicationOutcomeStatus;
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
        if (! $user->isCandidate()) {
            return false;
        }

        if (! $jobPosting->isPubliclyVisible()) {
            return false;
        }

        if ($user->worksAt($jobPosting->company)) {
            return false;
        }

        return ! Application::query()
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
     * The employer side of viewing, deliberately a separate ability
     * rather than widening view(). That one answers "is this mine?" for
     * a candidate, and quietly making it mean two things would let a
     * person who is both a candidate and a recruiter slip between them.
     *
     * Reviewing is open to everyone on the hiring team: reading
     * applications is the work, not a privilege.
     */
    public function review(User $user, Application $application): bool
    {
        return $user->worksAt($application->jobPosting->company);
    }

    public function reviewAny(User $user, JobPosting $jobPosting): bool
    {
        return $user->worksAt($jobPosting->company);
    }

    public function updateStage(User $user, Application $application): bool
    {
        return $this->review($user, $application);
    }

    /**
     * Moving many applications at once is a heavier act than moving one,
     * and an accidental sweep is hard to undo, so it stays with the
     * people who answer for the hire.
     */
    public function bulkUpdateStage(User $user, JobPosting $jobPosting): bool
    {
        return $user->canManage($jobPosting->company);
    }

    /**
     * A CV is the most private thing a candidate hands over. It is served
     * through this check rather than from a guessable public URL, and
     * only to the company they actually applied to.
     */
    public function downloadResume(User $user, Application $application): bool
    {
        return $this->review($user, $application);
    }

    /**
     * Withdraw is only meaningful while the application is still active.
     */
    public function withdraw(User $user, Application $application): bool
    {
        return $this->view($user, $application)
            && $application->outcome_status === ApplicationOutcomeStatus::Active;
    }
}
