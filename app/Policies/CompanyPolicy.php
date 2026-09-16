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
}
