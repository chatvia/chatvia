<?php

namespace App\Http\Middleware\Access;

use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route by a per-agent team permission, declared once on the route:
 * `agent.permission:agent.topics.manage`. Resolves the route-bound `{agent}`
 * and asks {@see has_permission_to_agent()}: admins and team owners pass;
 * everyone else needs the named permission on their current team AND access
 * to that agent.
 *
 * Denied requests get a 403 by default. Pass a second argument for sections
 * that should look like they do not exist to a teammate without the
 * permission: `agent.permission:agent.identity.manage,404`.
 *
 * Mirrors {@see EnsureTeamPermission} (team-level permissions). Prefer this
 * middleware over an inline `has_permission_to_agent()` check in a
 * controller; a few controllers still check inline (with a 404) and can move
 * their requirement here.
 */
class EnsureAgentPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string $abortStatus = '403'): Response
    {
        if (! $request->user()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $agent = $request->route('agent');

        if (! $agent instanceof Agent) {
            abort(Response::HTTP_NOT_FOUND);
        }

        abort_if(! has_permission_to_agent($agent, $permission), (int) $abortStatus);

        return $next($request);
    }
}
