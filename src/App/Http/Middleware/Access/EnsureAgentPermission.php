<?php

namespace App\Http\Middleware\Access;

use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route by a per-agent team permission. Apply as
 * `agent.permission:agent.topics.manage`. Resolves the route-bound `{agent}`
 * and delegates to {@see has_permission_to_agent()} — the exact gate the
 * controllers used to restate inline in every mutating method. Admins and team
 * owners pass; everyone else needs the named permission on their current team
 * for that agent.
 *
 * Mirrors {@see EnsureUserHasCapability}. Use this on `agents/{agent}` route
 * groups so the permission is declared once on the route instead of copy-pasted
 * into each controller action.
 */
class EnsureAgentPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()) {
            abort(401);
        }

        $agent = $request->route('agent');

        if (! $agent instanceof Agent) {
            abort(404);
        }

        abort_if(! has_permission_to_agent($agent, $permission), 403);

        return $next($request);
    }
}
