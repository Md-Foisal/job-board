<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Where signing out of the admin panel lands. The panel has no sign-in
 * page of its own, so Filament's default sends a signed-out person back
 * to the panel itself; that bounce stores the panel as the page to return
 * to, and whoever signs in next on the same browser -- an employer, a
 * candidate -- is taken to the panel and refused. Signing out goes where
 * the rest of the application's sign-out goes instead.
 */
class AdminLogoutResponse implements LogoutResponse
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->to('/');
    }
}
