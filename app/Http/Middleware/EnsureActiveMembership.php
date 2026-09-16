<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every company-scoped route. The company is named in the URL
 * rather than held in the session, so a link can be bookmarked, shared
 * with a colleague, or opened in a second tab without the two windows
 * fighting over which company is "current" -- which also means the URL
 * alone decides what is being asked for, and this is the one place that
 * checks whether the person asking is entitled to it.
 *
 * Entitlement is a live membership, not a stored role: the moment a
 * company marks someone inactive, the next request stops here.
 */
class EnsureActiveMembership
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->route('company');

        if (! $company instanceof Company) {
            abort(404);
        }

        $user = $request->user();

        if (! $user || ! $user->worksAt($company)) {
            abort(403, 'You do not have access to this company.');
        }

        return $next($request);
    }
}
