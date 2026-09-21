<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of anyone whose account has been suspended.
 *
 * Refusing the sign-in is not enough on its own: someone suspended while
 * signed in -- or holding a remember-me cookie -- would otherwise carry on
 * as if nothing had happened. This runs on every web request, so the next
 * thing a suspended person does signs them out.
 */
class EnsureAccountIsActive
{
    public const MESSAGE = 'This account has been suspended. If you think this is a mistake, contact support.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->account_status !== AccountStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
