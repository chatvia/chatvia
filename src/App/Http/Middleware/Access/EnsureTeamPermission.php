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
 */
class EnsureTeamPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()) {
            abort(401);
        }

        abort_if(! has_team_permission($permission), 403);

        return $next($request);
    }
}
