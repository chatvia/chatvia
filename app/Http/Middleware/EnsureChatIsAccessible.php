<?php

namespace App\Http\Middleware;

use App\Support\Agent;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnsureChatIsAccessible
{
    public function handle(Request $request, Closure $next)
    {
        $subdomain = $request->subdomain();
        $path = $request->path();

        if (str_starts_with($path, 'chat')) {
            $agent = Agent::resolveAgentFromSubdomain($subdomain);

            if (!$agent) {
                return Inertia::render('agents/chat/AgentNotFound');
            }

            if (! $agent->is_public) {
                if ($request->route()->getName() !== 'chat.login') {
                    return to_route('chat.login');
                }
            }

            if ($request->route()->getName() === 'chat.login' && $agent->is_public) {
                return to_route('chat.home');
            }

            if (! $agent->is_active) {
                return Inertia::render('agents/chat/AgentDisabled');
            }
        }

        return $next($request);
    }
}
