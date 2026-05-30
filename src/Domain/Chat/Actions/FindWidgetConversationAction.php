<?php

namespace Domain\Chat\Actions;

use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;

/**
 * Resolve a widget-side conversation by `(agent, session, conversation)` —
 * the conversation must belong to the requesting session, and the session
 * must belong to the requesting agent. Returns null when any link fails so
 * callers can decide the response shape (some endpoints 404, others fall
 * back to an "AI" placeholder).
 *
 * Scoping by agent is non-negotiable: without it a caller can submit a
 * session id from agent A while claiming to act on agent B.
 */
class FindWidgetConversationAction
{
    public function execute(
        string $conversationExternalId,
        string $sessionExternalId,
        ?Agent $agent = null,
    ): ?ChatConversation {
        return ChatConversation::query()
            ->where('external_id', $conversationExternalId)
            ->whereHas('session', function ($q) use ($sessionExternalId, $agent): void {
                $q->where('external_id', $sessionExternalId);
                if ($agent !== null) {
                    $q->where('agent_id', $agent->id);
                }
            })
            ->first();
    }
}
