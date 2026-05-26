<?php

namespace Domain\Chat\Actions;

use Domain\Agents\Models\Agent;
use Domain\Chat\Data\HandoffSummaryData;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Support\Collection;

class ListPendingHandoffsAction
{
    /**
     * @return Collection<int, HandoffSummaryData>
     */
    public function execute(Agent $agent): Collection
    {
        return ChatConversation::query()
            ->with(['session.user', 'lead', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->whereHas('session', fn ($q) => $q->where('agent_id', $agent->id))
            ->where('handoff_status', 'pending')
            ->where('visible', true)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (ChatConversation $conversation) => new HandoffSummaryData(
                conversation_id: $conversation->id,
                user_name: $conversation->displayName() ?? 'Visitor',
                user_email: $conversation->displayEmail(),
                last_message: $conversation->messages->first()?->content,
                last_message_at: $conversation->messages->first()?->created_at?->toIso8601String(),
                requested_at: $conversation->updated_at->toIso8601String(),
                session_id: $conversation->chat_session_id,
            ));
    }
}
