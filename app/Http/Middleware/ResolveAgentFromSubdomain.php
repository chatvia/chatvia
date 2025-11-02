<?php

namespace App\Http\Middleware;

use App\Services\Agents\Agent;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResolveAgentFromSubdomain
{
    public function handle(Request $request, Closure $next)
    {
        $subdomain = $request->subdomain();

        if ($subdomain) {
            $agent = Agent::resolveAgentFromSubdomain($request->subdomain());

            if (! $agent) {
                return Inertia::render('agents/chat/AgentNotFound');
            }
        }

        $request->merge([
            'agent' => $subdomain,
        ]);

        return $next($request);
    }
}
