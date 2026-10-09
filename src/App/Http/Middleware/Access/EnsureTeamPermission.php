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
 * Mirrors {@see EnsureAgentPermission} (per-agent permissions). Declare the
 * requirement on the route instead of an inline `has_team_permission()` check
 * in the controller.
 *
 * The optional second argument is the abort status for a denied teammate
 * (default 403). Pass `404` for sections that should look like they do not
 * exist to a teammate without the permission:
 * `team.permission:account.functions,404`.
 */
class EnsureTeamPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string $abortStatus = '403'): Response
    {
        if (! $request->user()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        abort_if(! has_team_permission($permission), (int) $abortStatus);

        return $next($request);
    }
}
