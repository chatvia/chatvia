<?php

namespace App\Http\Middleware\Chat;

use App\Http\Middleware\Auth\LogoutDeactivatedUser;
use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Access control for the hosted chat (`chat` group). In order:
 *
 *  1. The agent must exist and its Hosted Chat surface must be on; otherwise
 *     the visitor sees the not-found page.
 *  2. Puts the agent owner's id on the request (`agent_user_id`) for the
 *     message-capacity gate and the usage endpoint.
 *  3. A protected agent (`is_public = false`) needs a signed-in user: guests
 *     go to the chat login. On a public agent the login page sends people
 *     back to the chat home.
 *  4. A signed-in user on a protected agent must be allowed in:
 *     - on a conversation route (`{conversationId}`), they must own that
 *       conversation (the owner marker the chat memory stamps on create);
 *     - on any other route, they must have access to the agent itself.
 *     The web session is shared across agent subdomains, so without the
 *     second check a chatter granted agent A could open private agent B by
 *     changing the subdomain or the `?agent=` parameter.
 *
 * Deactivated accounts are signed out earlier in the group by
 * {@see LogoutDeactivatedUser}.
 */
class EnsureChatIsAccessible
{
    /** Routes a guest may open on a protected agent, besides the login pages. */
    private const array PUBLIC_ROUTES = ['chat.appearance'];

    public function handle(Request $request, Closure $next): Response|InertiaResponse
    {
        if (! str_starts_with($request->path(), 'chat')) {
            return to_route('chat.home');
        }

        $agent = AgentLocator::resolveAgentFromSubdomain($request->agentSlug());

        // A disabled Hosted Chat surface looks as if the agent did not exist.
        // The widget and bar surfaces are gated separately (widget settings).
        if (! $agent instanceof Agent || ! $agent->surfaceEnabled(ConversationSource::HostedChat)) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        $request->attributes->set('agent_user_id', $agent->user_id);

        $routeName = (string) $request->route()?->getName();
        $user = Auth::guard('web')->user();

        $isProtected = $agent->is_public === false;

        if ($isProtected && $user === null && ! $this->isOpenToGuests($routeName)) {
            return to_route('chat.login.show');
        }

        if ($agent->is_public && $routeName === 'chat.login.show') {
            return to_route('chat.home');
        }

        if ($isProtected && $user instanceof User) {
            $this->authorizeSignedInUser($request, $agent, $user);
        }

        return $next($request);
    }

    private function isOpenToGuests(string $routeName): bool
    {
        return str_starts_with($routeName, 'chat.login') || in_array($routeName, self::PUBLIC_ROUTES, true);
    }

    private function authorizeSignedInUser(Request $request, Agent $agent, User $user): void
    {
        $conversationId = $request->route('conversationId');

        if (! blank($conversationId)) {
            abort_unless(ChatCache::isOwnedBy($conversationId, $user->id, $agent->workspaceExternalId()), Response::HTTP_FORBIDDEN);

            return;
        }

        abort_unless($user->canAccessAgent($agent), Response::HTTP_FORBIDDEN, __('You do not have access to this agent.'));
    }
}
