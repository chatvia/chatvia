<?php

namespace App\Http\Middleware\Access;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route by a team-level (non-agent) permission. Apply as
 * `team.permission:account.chatters.manage`. Delegates to
 * {@see has_team_permission()} — admins and team owners pass; everyone else
 * needs the named permission on their current team.
 *
 * Mirrors {@see EnsureUserHasCapability}. Use this to replace repeated inline
 * `abort_unless(has_team_permission('...'), 404)` preambles in Settings/Team
 * controllers with a single declaration on the route.
 *
 * The optional second argument is the abort status for a denied teammate
 * (default 403). Pass `404` for sections that should read as non-existent to an
 * unauthorized teammate — `team.permission:account.functions,404` — preserving
 * the "hide the section's existence" behaviour the Integrations/Library pages
 * had when they gated inline.
 */
class EnsureTeamPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string $abortStatus = '403'): Response
    {
        if (! $request->user()) {
            abort(401);
        }

        abort_if(! has_team_permission($permission), (int) $abortStatus);

        return $next($request);
    }
}
