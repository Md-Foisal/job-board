<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every moderation table decides, row by row and action by action, whether
 * the member of staff looking at it works at that row's company. Loading
 * their active memberships once here lets User::worksAt() answer from memory.
 *
 * It reloads on every request rather than keeping what an earlier one
 * loaded, so a membership that ended a moment ago is never trusted.
 */
class LoadStaffMemberships
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->user()?->load('activeMemberships');

        return $next($request);
    }
}
