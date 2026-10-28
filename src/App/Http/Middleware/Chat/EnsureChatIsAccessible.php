<?php

namespace App\Http\Middleware\Chat;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatViewer;
use App\Http\Middleware\Auth\LogoutDeactivatedUser;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Access control for the hosted chat (`chat` group). In order:
 *
 *  1. The agent's Hosted Chat surface must be on and its workspace switched on
 *     ({@see WorkspaceActivation}); otherwise the visitor sees the not-found
 *     page (an unknown agent never gets here: {@see ResolveHostedChatAgent}
 *     answers first).
 *  2. Puts the agent's workspace id on the request (`agent_workspace_id`) for the
 *     message-capacity gate and the usage endpoint.
 *  3. A protected agent (`is_public = false`) needs a signed-in chatter or
 *     staff account ({@see HostedChatViewer}): guests
 *     go to the chat login.
 *  4. A signed-in user on a protected agent must have access to the agent
 *     itself on every route, and on a conversation route (`{conversationId}`)
 *     must also own that conversation on this agent (the owner marker the
 *     chat memory stamps on create). One chat session serves every agent, so
 *     without the agent check a chatter granted agent A could open private
 *     agent B by changing the `/chat/{agentSlug}` segment, and a revoked
 *     account could keep chatting through an old conversation id.
 *
 * The login and appearance routes are outside this group. Deactivated
 * accounts are signed out earlier in the group by
 * {@see LogoutDeactivatedUser}.
 */
class EnsureChatIsAccessible
{
    public function __construct(
        private readonly WorkspaceActivation $activation,
    ) {}

    public function handle(Request $request, Closure $next): Response|InertiaResponse
    {
        $agent = HostedChatAgent::from($request);

        // A disabled Hosted Chat surface, or a deactivated workspace, looks as
        // if the agent did not exist. The widget and bar surfaces are gated
        // separately (widget settings and EnsureWidgetIsAccessible).
        if (! $agent instanceof Agent
            || ! $agent->surfaceEnabled(ConversationSource::HostedChat)
            || ! $this->activation->isActive($agent->workspace_id)) {
            return Inertia::render('chat/pages/AgentNotFound');
        }

        $request->attributes->set('agent_workspace_id', $agent->workspace_id);

        $account = HostedChatViewer::account();
        $isProtected = $agent->is_public === false;

        if ($isProtected && $account === null) {
            return to_route('chat.login.show');
        }

        if ($isProtected && $account !== null) {
            $this->authorizeSignedInAccount($request, $agent, $account);
        }

        return $next($request);
    }

    private function authorizeSignedInAccount(Request $request, Agent $agent, Chatter|User $account): void
    {
        abort_unless($account->canAccessAgent($agent), Response::HTTP_FORBIDDEN, __('You do not have access to this agent.'));

        $conversationId = $request->route('conversationId');

        // Another account's conversation, or one started on another agent,
        // answers like an unknown id, so its id does not tell that it exists.
        if (! blank($conversationId)) {
            abort_unless(ChatCache::isOwnedBy($conversationId, HostedChatViewer::data(), $agent), Response::HTTP_NOT_FOUND);
        }
    }
}
