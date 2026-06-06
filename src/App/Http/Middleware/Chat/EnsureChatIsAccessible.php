<?php

namespace App\Http\Middleware\Chat;

use Closure;
use Domain\Agent\Services\Agent;
use Domain\Conversation\Services\Memory\ChatCache;
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

        // A deactivated account loses access immediately, even mid-session. The
        // login-time gate (LoginUserToAgentAction / sign-in token) can't catch a
        // session that's already open, so re-check on every chat request and
        // sign the user out — otherwise a chatter deactivated while logged in
        // keeps browsing and chatting until their session happens to expire.
        if ($userAuthenticated && Auth::guard('web')->user()->isDeactivated()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('chat.login.show')->with('error', 'Your account has been deactivated.');
        }

        if ($agent->is_public === false && $userAuthenticated === false) {
            $isAuthLogin = str_starts_with($currentRouteName, 'chat.login');

            if ($isAuthLogin === false && ! in_array($currentRouteName, $allowedRoutesWithoutAuth, true)) {
                return to_route('chat.login.show');
            }
        }

        if ($request->route()?->getName() === 'chat.login.show' && $agent->is_public) {
            return to_route('chat.home');
        }

        // Per-conversation ownership check on protected agents. The
        // ownership marker is keyed `user:<auth-id>:<conv-id>` at
        // conversation creation (see ChatCache::rememberOwner) — a
        // `has()` under the current chatter's id proves the same user
        // created it, no DB hit. Different user → different key → miss → 403.
        if ($agent->is_public === false && $userAuthenticated) {
            $conversationId = $request->route('conversationId');

            if (! blank($conversationId) && ! ChatCache::isOwnedBy($conversationId, Auth::id())) {
                abort(403);
            }
        }

        return $next($request);
    }
}
