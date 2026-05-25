<?php

namespace Domain\Chat\QueryBuilders;

use Domain\Chat\Models\ChatMessage;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of ChatMessage
 *
 * @extends Builder<TModel>
 */
class ChatMessageQueryBuilder extends Builder
{
    public function forAgentConversation(int $agentId, int $conversationId): self
    {
        return $this
            ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agentId))
            ->where('chat_conversation_id', $conversationId);
    }
}
