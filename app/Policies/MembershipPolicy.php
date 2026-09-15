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

    /**
     * Three separate things have to hold before one person may change
     * another's place on the team, and each closes a different door.
     *
     * You cannot act on someone who outranks you, so a manager can
     * settle the team around them but cannot reach the owner who
     * appointed them. You cannot act on yourself, which is what stops a
     * manager quietly promoting themselves into an owner -- changing
     * your own standing is not a roster edit, and leaving a company is
     * its own separate act. And the last remaining owner cannot be moved
     * at all: ownership changes hands by promoting a successor first,
     * never by vacating the seat and hoping someone fills it.
     */
    public function update(User $user, Membership $membership): bool
    {
        if ($user->id === $membership->user_id) {
            return false;
        }

        $actingRole = $user->roleAt($membership->company);

        if ($actingRole === null || ! $user->canManage($membership->company)) {
            return false;
        }

        if ($actingRole->rank() < $membership->role->rank()) {
            return false;
        }

        // Kept as a second layer rather than as the load-bearing check:
        // the two rules above already make "acting on the last owner"
        // unreachable (only an owner outranks an owner, and with one owner
        // left that owner would be acting on themselves). It stays because
        // self-removal, which those two rules do not cover, needs exactly
        // this guard -- see Membership::isLastActiveOwner().
        return ! $membership->isLastActiveOwner();
    }

    /**
     * Removing someone from a company is a status change, never a row
     * deletion -- the record of who worked there and when is what makes
     * past postings and decisions attributable. It answers to exactly
     * the same three rules as a role change.
     */
    public function deactivate(User $user, Membership $membership): bool
    {
        return $this->update($user, $membership);
    }
}
