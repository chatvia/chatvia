<?php

namespace Domain\Agent\Actions\ChatLog;

use Domain\Agent\Data\Resources\ChatLog\Handoff\ChatLogHandoffStatusData;
use Domain\Agent\Data\Resources\ChatLog\Messages\ChatLogMessagesResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Form\Models\FormSubmission;

/**
 * Paginated messages for a chat-log conversation plus its handoff state and
 * any lead row attached to the same conversation.
 */
class ListConversationMessagesAction
{
    public function execute(Agent $agent, Conversation $conversation, int $page = 1): ChatLogMessagesResourceData
    {
        $messages = Message::forAgentConversation($agent->id, $conversation->id)
            ->with('llm')
            ->orderBy('created_at')
            ->orderByRaw("CASE WHEN role = 'user' THEN 0 ELSE 1 END")
            ->paginate(50, page: $page);

        // The conversation's most recent SUBMITTED form (keyed by internal
        // chat_conversation_id). Pending rows — a form rendered but never filled
        // — are not leads and must not surface the Lead panel.
        $lead = FormSubmission::query()
            ->where('agent_id', $agent->id)
            ->where('chat_conversation_id', $conversation->id)
            ->where('status', FormSubmission::STATUS_SUBMITTED)
            ->latest('id')
            ->first();

        return ChatLogMessagesResourceData::fromPaginator(
            messages: $messages,
            handoff: ChatLogHandoffStatusData::fromConversation($conversation),
            lead: $lead,
        );
    }
}
