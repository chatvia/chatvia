<?php

namespace App\Http\Middleware;

use App\Auth\Models\User;
use Closure;
use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /api/v1/agents/{agent}/... endpoints: the authenticated account
 * (the bearer-token owner) must own the agent's team.
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

        if ($principal instanceof User && $principal->ownsTeam($agent->team)) {
            return $next($request);
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
