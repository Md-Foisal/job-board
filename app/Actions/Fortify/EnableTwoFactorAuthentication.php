<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as FortifyEnableTwoFactorAuthentication;

class EnableTwoFactorAuthentication extends FortifyEnableTwoFactorAuthentication
{
    use KeepStaffTwoFactor;

    /**
     * A forced re-enable replaces the secret but leaves the account marked
     * as confirmed, so a staff member could end up relying on a secret no
     * authenticator has ever produced a code for.
     */
    public function __invoke($user, $force = false)
    {
        if ($force === true) {
            $this->refuseForConfirmedStaff($user);
        }

        parent::__invoke($user, $force);
    }
}
