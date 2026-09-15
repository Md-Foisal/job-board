<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Membership;
use App\Models\User;

class MembershipPolicy
{
    /**
     * Who is on the team, and who may change that, are both
     * ownership-level questions -- a plain member does not get the
     * roster page at all.
     */
    public function viewAny(User $user, Company $company): bool
    {
        return $user->canManage($company);
    }

    public function update(User $user, Membership $membership): bool
    {
        return $user->canManage($membership->company);
    }

    /**
     * Removing someone from a company is a status change, never a row
     * deletion -- the record of who worked there and when is what makes
     * past postings and decisions attributable.
     */
    public function deactivate(User $user, Membership $membership): bool
    {
        return $this->update($user, $membership);
    }
}
