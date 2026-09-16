<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Changing how the company presents itself to candidates is an
     * ownership-level decision, so a plain member cannot do it. There is
     * no view ability: the company profile is public by design -- it
     * exists to be read by people deciding whether to apply.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->canManage($company);
    }

    /**
     * Platform-side decisions about a company: verify it, ask it for
     * documents, ban it, or undo any of those. One ability covers all of
     * them because they sit at the same level of trust -- nothing in the
     * design splits them further -- and because the disqualifying fact is
     * the same for each: staff cannot rule on a company they work for.
     */
    public function moderate(User $user, Company $company): bool
    {
        return $user->isActiveStaff()
            && ! $user->worksAt($company);
    }
}
