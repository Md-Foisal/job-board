<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisableTwoFactorAuthentication;

class DisableTwoFactorAuthentication extends FortifyDisableTwoFactorAuthentication
{
    use KeepStaffTwoFactor;

    public function __invoke($user)
    {
        $this->refuseForConfirmedStaff($user);

        parent::__invoke($user);
    }
}
