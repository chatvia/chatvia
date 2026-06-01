<?php

namespace App\Http\Middleware\Chat;

use Closure;
use Domain\Agents\Services\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EnsureChatIsAccessible
{
    public function handle(Request $request, Closure $next)
    {
        $slug = $request->agentSlug();
        $path = $request->path();

        if (! str_starts_with($path, 'chat')) {
            return to_route('chat.home');
        }

        $agent = Agent::resolveAgentFromSubdomain($slug);

        if (! $agent) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        $request->attributes->set('agent_user_id', $agent->user_id);

        $allowedRoutesWithoutAuth = [
            'chat.appearance',
        ];

        $currentRouteName = $request->route()?->getName();

        $userAuthenticated = Auth::guard('web')->check();

        if ($agent->is_public === false && $userAuthenticated === false) {
            $isAuthLogin = str_starts_with($currentRouteName, 'chat.login');

            if ($isAuthLogin === false && ! in_array($currentRouteName, $allowedRoutesWithoutAuth, true)) {
                return to_route('chat.login.show');
            }
        }

        if ($request->route()?->getName() === 'chat.login.show' && $agent->is_public) {
            return to_route('chat.home');
        }

        return $next($request);
    }
}
