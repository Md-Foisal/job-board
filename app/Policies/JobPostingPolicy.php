<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;

class JobPostingPolicy
{
    /**
     * Whether the user can view the list of job postings belonging to
     * the given company (e.g. the employer's own job management page).
     */
    public function viewAny(User $user, Company $company): bool
    {
        return $user->worksAt($company);
    }

    /**
     * Whether the user can view this job posting. A publicly visible
     * posting (approved, active, unexpired, company in good standing) is
     * open to guests too -- hence the nullable $user. Anyone else needs
     * to be a member of the owning company (e.g. to preview a draft).
     */
    public function view(?User $user, JobPosting $jobPosting): bool
    {
        if ($jobPosting->isPubliclyVisible()) {
            return true;
        }

        return $user !== null && $user->worksAt($jobPosting->company);
    }

    /**
     * Whether the user can create a job posting for the given company.
     */
    public function create(User $user, Company $company): bool
    {
        return $user->canManage($company);
    }

    public function update(User $user, JobPosting $jobPosting): bool
    {
        return $user->canManage($jobPosting->company);
    }

    /**
     * A job posting can only be deleted outright if no one has applied
     * to it yet -- once applications exist, they become part of the
     * candidate's application history and must be preserved.
     */
    public function delete(User $user, JobPosting $jobPosting): bool
    {
        return $user->canManage($jobPosting->company)
            && $jobPosting->applications()->doesntExist();
    }

    public function restore(User $user, JobPosting $jobPosting): bool
    {
        return false;
    }

    public function forceDelete(User $user, JobPosting $jobPosting): bool
    {
        return false;
    }

    public function close(User $user, JobPosting $jobPosting): bool
    {
        return $user->canManage($jobPosting->company);
    }

    public function reopen(User $user, JobPosting $jobPosting): bool
    {
        return $user->canManage($jobPosting->company);
    }

    public function extend(User $user, JobPosting $jobPosting): bool
    {
        return $user->canManage($jobPosting->company);
    }

    public function duplicate(User $user, JobPosting $jobPosting): bool
    {
        return $user->canManage($jobPosting->company);
    }
}
