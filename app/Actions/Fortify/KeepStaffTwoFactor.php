<?php

namespace App\Actions\Fortify;

use App\Models\User;

/**
 * Staff must keep two-factor on. Fortify's own routes can turn it off or
 * swap the secret without going through the security page, so the rule
 * sits in the actions those routes call rather than only in the page.
 */
trait KeepStaffTwoFactor
{
    protected function refuseForConfirmedStaff(User $user): void
    {
        // An unconfirmed secret is an abandoned setup, and clearing it is
        // what lets a staff member start again, so only a working second
        // factor is protected.
        abort_if($user->isStaff() && $user->two_factor_confirmed_at !== null, 403, __('Two-factor authentication is required for staff accounts.'));
    }
}
