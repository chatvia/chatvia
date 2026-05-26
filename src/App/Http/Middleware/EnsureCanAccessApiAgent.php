<?php

namespace App\Http\Middleware;

use App\Auth\Models\User;
use Closure;
use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /api/v1/agents/{agent}/... endpoints.
 *
 * The authenticated principal may be either:
 *  - a User (master token) — must own the agent's team, OR
 *  - an Agent (agent token) — must match the route's resolved agent.
 *
 * Built-in connector MCP servers and similar internal-only entities are filtered
 * elsewhere; this middleware only handles agent ownership.
 */
class EnsureCanAccessApiAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Agent|null $agent */
        $agent = $request->route('agent');

        if (! $agent instanceof Agent) {
            return $this->notFound();
        }

        $principal = $request->user();

        if ($principal instanceof Agent) {
            return $principal->getKey() === $agent->getKey()
                ? $next($request)
                : $this->forbidden();
        }

        if ($principal instanceof User) {
            return $principal->ownsTeam($agent->team)
                ? $next($request)
                : $this->forbidden();
        }

        return $this->forbidden();
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'You do not have access to this agent.'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Agent not found.'], 404);
    }
}
