<?php

namespace App\Http\Middleware\Chat;

use App\Http\Chat\HostedChatAgent;
use App\Http\Middleware\Auth\LogoutDeactivatedUser;
use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Agent\Models\Agent;
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
 *  1. The agent's Hosted Chat surface must be on; otherwise the visitor sees
 *     the not-found page (an unknown agent never gets here:
 *     {@see ResolveHostedChatAgent} answers first).
 *  2. Puts the agent owner's id on the request (`agent_user_id`) for the
 *     message-capacity gate and the usage endpoint.
 *  3. A protected agent (`is_public = false`) needs a signed-in user: guests
 *     go to the chat login.
 *  4. A signed-in user on a protected agent must be allowed in:
 *     - on a conversation route (`{conversationId}`), they must own that
 *       conversation (the owner marker the chat memory stamps on create);
 *     - on any other route, they must have access to the agent itself.
 *     One chat session serves every agent, so without the second check a
 *     chatter granted agent A could open private agent B by changing the
 *     `/chat/{agentSlug}` segment.
 *
 * The login and appearance routes are outside this group. Deactivated
 * accounts are signed out earlier in the group by
 * {@see LogoutDeactivatedUser}.
 */
class EnsureChatIsAccessible
{
    public function handle(Request $request, Closure $next): Response|InertiaResponse
    {
        $agent = HostedChatAgent::from($request);

        // A disabled Hosted Chat surface looks as if the agent did not exist.
        // The widget and bar surfaces are gated separately (widget settings).
        if (! $agent instanceof Agent || ! $agent->surfaceEnabled(ConversationSource::HostedChat)) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        $request->attributes->set('agent_user_id', $agent->user_id);

        $user = Auth::user();
        $isProtected = $agent->is_public === false;

        if ($isProtected && $user === null) {
            return to_route('chat.login.show');
        }

        if ($isProtected && $user instanceof User) {
            $this->authorizeSignedInUser($request, $agent, $user);
        }

        return $next($request);
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
