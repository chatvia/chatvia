<?php

namespace App\Http\Middleware;

use App\Support\Agent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EnsureChatIsAccessible
{
    public function handle(Request $request, Closure $next)
    {
        $subdomain = $request->subdomain();
        $path = $request->path();

        if (! str_starts_with($path, 'chat')) {
            return to_route('chat.home');
        }

        $agent = Agent::resolveAgentFromSubdomain($subdomain);

        if (! $agent) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        $request->attributes->set('agent_user_id', $agent->user_id);

        $allowedRoutesWithoutAuth = [
            'chat.appearance',
        ];

        $currentRouteName = $request->route()?->getName();

        $memberAuthenticated = Auth::guard('member')->check();

        if ($agent->is_public === false && $memberAuthenticated === false) {
            $isAuthLogin = str_starts_with($currentRouteName, 'chat.login');

            if ($isAuthLogin === false && ! in_array($currentRouteName, $allowedRoutesWithoutAuth, true)) {
                return to_route('chat.login.show');
            }
        }

        if ($request->route()?->getName() === 'chat.login.show' && $agent->is_public) {
            return to_route('chat.home');
        }

        if (! $agent->is_active) {
            return Inertia::render('agents/chat/AgentDisabled');
        }

        return $next($request);
    }
}
