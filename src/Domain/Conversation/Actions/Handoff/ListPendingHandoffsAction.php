<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Handoff\HandoffSummaryData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Collection;

class ListPendingHandoffsAction
{
    /**
     * @return Collection<int, HandoffSummaryData>
     */
    public function execute(Agent $agent): Collection
    {
        return Conversation::query()
            ->forAgent($agent->id)
            ->with(['session.user', 'lead', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->where('handoff_status', 'pending')
            ->where('is_visible', true)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $conversation): HandoffSummaryData => new HandoffSummaryData(
                // HandoffSummaryData exposes the public-facing identifiers
                // (uuid strings), not the autoincrement PKs.
                conversation_id: $conversation->external_id,
                user_name: $conversation->displayName() ?? 'Visitor',
                user_email: $conversation->displayEmail(),
                last_message: $conversation->messages->first()?->content,
                last_message_at: $conversation->messages->first()?->created_at?->toIso8601String(),
                requested_at: $conversation->updated_at->toIso8601String(),
                session_id: $conversation->session->external_id,
            ));
    }
}
