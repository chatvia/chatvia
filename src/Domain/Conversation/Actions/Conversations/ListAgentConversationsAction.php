<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Agent\Models\Agent;
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
        return Conversation::query()
            ->forAgent($agent->id)
            ->onlyVisible()
            ->orderByLatestActivity()
            ->paginate($perPage)
            ->through(fn (Conversation $c): ConversationSummaryData => ConversationSummaryData::fromModel($c));
    }
}
