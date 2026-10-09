<?php

namespace App\Http\Middleware\Access;

use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard and API gate (`can.access.agent`): the signed-in user may open the
 * route-bound `{agent}`. Delegates to the `access-agent` gate (admins, the
 * owning workspace, and teammates whose role covers the agent); anyone else
 * gets a 403.
 *
 * Routes without an `{agent}` model pass through unchanged, so the alias can
 * sit on a whole route group.
 */
class EnsureUserCanAccessAgent
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $request->route('agent');

        if ($agent instanceof Agent) {
            Gate::authorize('access-agent', $agent);
        }

        return $next($request);
    }
}
