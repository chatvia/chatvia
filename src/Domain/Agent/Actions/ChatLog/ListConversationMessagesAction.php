<?php

namespace Domain\Agent\Actions\ChatLog;

use Domain\Agent\Data\Resources\ChatLog\Handoff\ChatLogHandoffStatusData;
use Domain\Agent\Data\Resources\ChatLog\Messages\ChatLogMessagesResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Media\GeneratedMediaArtifacts;
use Domain\Form\Models\FormSubmission;
use Support\License\Entitlements;

/**
 * Paginated messages for a chat-log conversation, each reply with the
 * knowledge it was given and its generated images and audio as they stand
 * now, plus the conversation's handoff state and any lead row attached to
 * the same conversation. Leads need a license key: without one there is none.
 */
class ListConversationMessagesAction
{
    public function __construct(
        private readonly GeneratedMediaArtifacts $generatedMedia,
        private readonly Entitlements $entitlements,
    ) {}

    public function execute(Agent $agent, Conversation $conversation, int $page = 1): ChatLogMessagesResourceData
    {
        $messages = Message::forAgentConversation($agent->id, $conversation->id)
            // Only the chunk text: the preview needs nothing else, and a
            // chunk's vectors are large.
            ->with(['llm', 'sources.chunk:id,chunk'])
            ->orderBy('created_at')
            ->orderByRaw("CASE WHEN role = 'user' THEN 0 ELSE 1 END")
            // The last tiebreak makes the order total, so no message repeats
            // or goes missing between two pages.
            ->orderBy('id')
            ->paginate(50, page: $page);

        $this->generatedMedia->forChatLog($messages->getCollection());

        // The conversation's most recent SUBMITTED form (keyed by internal
        // chat_conversation_id). Pending rows — a form rendered but never filled
        // — are not leads and must not surface the Lead panel.
        $lead = $this->entitlements->capabilities()
            ? FormSubmission::query()
                ->where('agent_id', $agent->id)
                ->where('chat_conversation_id', $conversation->id)
                ->submitted()
                ->latest('id')
                ->first()
            : null;

        return ChatLogMessagesResourceData::fromPaginator(
            messages: $messages,
            handoff: ChatLogHandoffStatusData::fromConversation($conversation),
            lead: $lead,
        );
    }
}
