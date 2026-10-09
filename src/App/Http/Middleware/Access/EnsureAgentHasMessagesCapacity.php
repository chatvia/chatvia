<?php

namespace App\Http\Middleware\Access;

use Closure;
use Domain\Billing\Contracts\Entitlements;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hosted chat gate: the agent's workspace must still have messages left on
 * its plan. Billing is message-based, so when the owner's balance is used up
 * visitors see the "no messages" page instead of a chat that cannot answer.
 *
 * Runs in the `chat` group after EnsureChatIsAccessible, which puts the
 * agent owner's id on the request (`agent_user_id`). Always passes when
 * billing is turned off.
 */
class EnsureAgentHasMessagesCapacity
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response|InertiaResponse
    {
        $agentUserId = $request->attributes->get('agent_user_id');

        if (! $agentUserId) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        // Reads the owner's cached can-send flag (refreshed from the database
        // on a cache miss, after each consumption and every 5 minutes by
        // app:cache-message-usage).
        if (! $this->entitlements->hasMessageCapacity((int) $agentUserId)) {
            return Inertia::render('agents/chat/AgentNoMessages');
        }

        return $next($request);
    }
}
