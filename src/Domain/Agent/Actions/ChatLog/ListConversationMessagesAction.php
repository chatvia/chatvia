<?php

namespace Domain\Agent\Actions\ChatLog;

use Domain\Agent\Data\Resources\ChatLog\Handoff\ChatLogHandoffStatusData;
use Domain\Agent\Data\Resources\ChatLog\Messages\ChatLogMessagesResourceData;
use Domain\Agent\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Domain\Lead\Models\Lead;

/**
 * Paginated messages for a chat-log conversation plus its handoff state and
 * any lead row attached to the same conversation.
 */
class ListConversationMessagesAction
{
    public function execute(Agent $agent, ChatConversation $conversation, int $page = 1): ChatLogMessagesResourceData
    {
        $messages = ChatMessage::forAgentConversation($agent->id, $conversation->id)
            ->with('llm')
            ->orderBy('created_at')
            ->orderByRaw("CASE WHEN role = 'user' THEN 0 ELSE 1 END")
            ->paginate(50, page: $page);

        $lead = Lead::query()
            ->where('agent_id', $agent->id)
            ->where('chat_conversation_id', $conversation->id)
            ->first();

        return ChatLogMessagesResourceData::fromPaginator(
            messages: $messages,
            handoff: ChatLogHandoffStatusData::fromConversation($conversation),
            lead: $lead,
        );
    }
}
