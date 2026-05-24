<?php

namespace Domain\Chat\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class ChatMessageQueryBuilder extends Builder
{
    public function forAgentConversation(int $agentId, string $conversationId): self
    {
        return $this
            ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agentId))
            ->where('chat_conversation_id', $conversationId);
    }
}
