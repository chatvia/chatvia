<?php

namespace Domain\Conversation\Actions\Conversations;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Illuminate\Support\Str;

/**
 * Resolve a widget-side conversation by `(agent, session, conversation)` —
 * the conversation must belong to the requesting session, the session to the
 * requesting agent and, on a protected agent, to the signed-in chatter. A
 * thread the visitor cleared never resolves. Returns null when any link fails
 * so callers can decide the response shape (some endpoints 404, others fall
 * back to an "AI" placeholder).
 *
 * It applies the same rule as the widget's other conversation lookups
 * ({@see ConversationQueryBuilder::reachableByWidgetVisitor()}), plus the
 * session match, because these endpoints always name the session.
 */
class FindWidgetConversationAction
{
    public function execute(
        string $conversationExternalId,
        string $sessionExternalId,
        Agent $agent,
        ?User $chatter = null,
    ): ?Conversation {
        // External ids are UUIDs; anything else can never match (and would
        // make Postgres reject the query with a 500).
        if (! Str::isUuid($conversationExternalId) || ! Str::isUuid($sessionExternalId)) {
            return null;
        }

        return Conversation::query()
            ->where('external_id', $conversationExternalId)
            ->inSession($sessionExternalId, $agent->id)
            ->reachableByWidgetVisitor($agent->id, $chatter?->id)
            ->first();
    }
}
