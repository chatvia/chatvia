<?php

namespace Domain\Analytics\Actions\Topics;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;

/**
 * Dismisses an emergent topic suggestion for the agent: drops the suggestion
 * rows across ALL of the agent's conversations — not only the suggestion
 * window — so a later promote of the same label cannot bring the dismissed
 * rows back. The owning conversations keep analyzed_at, so the label is not
 * generated again.
 */
class DismissSuggestedTopicAction
{
    public function execute(Agent $agent, string $label): void
    {
        ConversationTopic::query()
            ->where('suggested_label', $label)
            ->whereIn('chat_conversation_id', Conversation::query()->select('id')->where('agent_id', $agent->id))
            ->delete();
    }
}
