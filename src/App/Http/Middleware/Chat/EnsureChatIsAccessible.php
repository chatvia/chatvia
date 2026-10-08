<?php

namespace App\Http\Middleware\Chat;

use Closure;
use Domain\Agent\Services\AgentLocator;
use Domain\Conversation\Enums\ConversationSource;
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

        $agent = AgentLocator::resolveAgentFromSubdomain($slug);

        if (! $agent) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        // A disabled Hosted Chat surface behaves as if the agent doesn't exist —
        // same not-found page the owner gets a 404-style screen for. The Widget /
        // Bar surfaces are gated separately (BuildWidgetSettingsAction).
        if (! $agent->surfaceEnabled(ConversationSource::HostedChat)) {
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
            $isAuthLogin = str_starts_with((string) $currentRouteName, 'chat.login');

            if ($isAuthLogin === false && ! in_array($currentRouteName, $allowedRoutesWithoutAuth, true)) {
                return to_route('chat.login.show');
            }
        }

        if ($request->route()?->getName() === 'chat.login.show' && $agent->is_public) {
            return to_route('chat.home');
        }

        // Access control on protected (private) agents for authenticated users.
        // Two complementary gates:
        //
        //  - Conversation-scoped routes ({conversationId} present): ownership of
        //    THAT conversation is the gate (ChatCache::rememberOwner marks the
        //    creator). A different user → miss → 403. This is the historical
        //    model and lets a conversation owner act on their own thread.
        //
        //  - Entry / non-conversation routes (home, conversations/create, agent
        //    switch, a brand-new stream): require membership/ownership of the
        //    agent itself (canAccessAgent). Membership is otherwise only checked
        //    at login/switch, and the web-guard session is shared across agent
        //    subdomains — so without this re-check a chatter granted agent A can
        //    reach private agent B (any tenant) by changing the subdomain or the
        //    ?agent= query param and start chatting on the owner's bill.
        if ($agent->is_public === false && $userAuthenticated) {
            $conversationId = $request->route('conversationId');

            if (! blank($conversationId)) {
                if (! ChatCache::isOwnedBy($conversationId, Auth::id(), $agent->workspaceExternalId())) {
                    abort(403);
                }
            } elseif (! Auth::guard('web')->user()->canAccessAgent($agent)) {
                abort(403, 'You do not have access to this agent.');
            }
        }

        return $next($request);
    }
}
