<?php

namespace App\Policies;

use App\Enums\AccountStatus;
use App\Enums\StaffRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Suspending an account is the heaviest thing the platform does to a
     * person, so it answers to three rules -- the platform-side twin of
     * the ones a company applies to its own members:
     *
     * only a super admin may do it; no one may do it to themselves; and
     * no one may do it to a peer or a superior. With only super admins
     * allowed at all, the last rule is what stops two of them from
     * suspending each other; it stays a rank comparison so a new staff
     * role slots in without rewriting it.
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

    /**
     * Erasing someone's personal data on request -- the one thing here
     * that cannot be undone -- takes the same three rules as suspending,
     * and cannot be done twice.
     */
    public function erase(User $user, User $target): bool
    {
        return $target->anonymized_at === null && $this->suspend($user, $target);
    }

    /**
     * Making a moderator is the only staff role the panel hands out; the
     * super admin role comes from the server (staff:grant). The person
     * must be someone who can actually do the job and be held to it: a
     * live account, an email address that is known to reach them, and a
     * password -- staff actions ask for it again before anything is taken
     * away from anyone.
     */
    public function makeModerator(User $user, User $target): bool
    {
        return $user->isActiveStaff()
            && $user->isSuperAdmin()
            && $user->id !== $target->id
            && ! $target->isStaff()
            && ! $target->trashed()
            && $target->anonymized_at === null
            && $target->account_status === AccountStatus::Active
            && $target->hasVerifiedEmail()
            && $target->hasPassword();
    }

    /**
     * Only the moderator role can be taken away here, for the same reason
     * only it can be given.
     */
    public function removeModerator(User $user, User $target): bool
    {
        return $user->isActiveStaff()
            && $user->isSuperAdmin()
            && $target->staff_role === StaffRole::Moderator;
    }
}
