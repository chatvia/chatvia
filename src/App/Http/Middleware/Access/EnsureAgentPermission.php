<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route by a per-agent team permission, declared once on the route:
 * `agent.permission:agent.topics.manage`. Resolves the route-bound `{agent}`
 * and asks {@see TeamPermissionPolicy::allowsOnAgent()}: admins and team
 * owners pass; everyone else needs the named permission on their current team
 * AND access to that agent.
 *
 * Denied requests get a 403 by default. Pass a second argument for sections
 * that should look like they do not exist to a teammate without the
 * permission: `agent.permission:agent.identity.manage,404`.
 *
 * Mirrors {@see EnsureTeamPermission} (team-level permissions). Declare the
 * requirement on the route instead of checking it inline in a controller.
 */
class EnsureAgentPermission
{
    public function __construct(
        private readonly TeamPermissionPolicy $permissions,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string $abortStatus = '403'): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $agent = $request->route('agent');

        if (! $agent instanceof Agent) {
            abort(Response::HTTP_NOT_FOUND);
        }

        abort_unless($this->permissions->allowsOnAgent($user, $agent, $permission), (int) $abortStatus);

        return $next($request);
    }
}
