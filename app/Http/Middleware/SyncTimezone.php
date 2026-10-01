<?php

namespace App\Http\Middleware;

use App\Support\LocalTime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an account's time zone with the browser it is being used from,
 * unless the person chose one by hand in their settings. The browser
 * reports its zone in a cookie (partials/timezone-cookie); the account
 * keeps a copy because an email has no browser to ask.
 *
 * Following the browser is what Slack and Notion do by default: someone
 * who flies from Dhaka to London sees London times on arrival.
 */
class SyncTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $zone = LocalTime::fromBrowser();

        if ($user !== null && $zone !== null && $user->timezone_automatic && $user->timezone !== $zone) {
            $user->timezone = $zone;
            $user->save();
        }

        return $next($request);
    }
}
