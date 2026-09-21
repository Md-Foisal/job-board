<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff accounts can hide postings, ban companies and suspend people, so
 * a stolen staff password is worth far more than an ordinary one. The
 * admin panel therefore refuses anyone who has not finished setting up
 * two-factor authentication, and sends them to the page where they can.
 *
 * It runs after Filament's own Authenticate, so by the time it sees a
 * user that user is already known to be active staff.
 */
class EnsureStaffHasTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
