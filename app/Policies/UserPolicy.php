<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Suspending an account is the heaviest thing the platform does to a
     * person, so it answers to three rules -- the platform-side twin of
     * the ones a company applies to its own members:
     *
     * only a super admin may do it; no one may do it to themselves; and
     * no one may do it to a peer or a superior. The last rule is what
     * stops two super admins from suspending each other, and what keeps
     * a moderator from reaching upward.
     *
     * Suspension is deliberately separate from a user deleting their own
     * account: someone the platform has suspended must not be able to
     * lift it by walking through the self-service restore flow.
     */
    public function suspend(User $user, User $target): bool
    {
        if (! $user->isActiveStaff() || ! $user->isSuperAdmin()) {
            return false;
        }

        if ($user->id === $target->id) {
            return false;
        }

        if ($target->isStaff() && $user->staff_role->rank() <= $target->staff_role->rank()) {
            return false;
        }

        return true;
    }

    /**
     * Lifting a suspension carries none of the rank rules above. Taking
     * something away has to be hard to do by mistake; giving it back does
     * not -- and if peers could not reinstate each other, a wrongly
     * suspended super admin would have no way out.
     */
    public function reinstate(User $user, User $target): bool
    {
        return $user->isActiveStaff()
            && $user->isSuperAdmin();
    }
}
