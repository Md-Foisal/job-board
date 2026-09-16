<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    /**
     * Inviting and un-inviting travel with the same authority that
     * manages the roster itself.
     *
     * Accepting an invitation is deliberately not an ability here: the
     * person accepting is by definition not yet on the team, so the
     * token in the link is what vouches for them, not a policy.
     */
    public function create(User $user, Company $company): bool
    {
        return $user->canManage($company);
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $user->canManage($invitation->company);
    }
}
