<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Agent\Models\Agent;
use Domain\Billing\Enums\Capability;
use Domain\Conversation\Data\Conversations\ConversationSummaryData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Paginated conversation list for an agent — used by `GET
 * /api/v1/agents/{agent}/conversations`.
 */
class ListAgentConversationsAction
{
    /**
     * @return LengthAwarePaginator<int, ConversationSummaryData>
     */
    public function execute(Agent $agent, int $perPage): LengthAwarePaginator
    {
        // Every conversation in the page belongs to this one agent, so the
        // plan-gated sentiment/tone flags are constant across the page. Resolve
        // them once here instead of lazy-loading agent->team for every row.
        $team = $agent->team;
        $sentimentEnabled = $team?->hasCapability(Capability::Sentiment) ?? false;
        $toneEnabled = $team?->hasCapability(Capability::Tone) ?? false;

        return Conversation::query()
            ->forAgent($agent->id)
            ->onlyVisible()
            ->orderByLatestActivity()
            ->paginate($perPage)
            ->through(fn (Conversation $c): ConversationSummaryData => ConversationSummaryData::fromModel($c, $sentimentEnabled, $toneEnabled));
    }
}
