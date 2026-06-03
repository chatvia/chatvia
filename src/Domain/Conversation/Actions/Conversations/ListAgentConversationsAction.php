<?php

namespace Domain\Conversation\Actions\Conversations;

use App\Http\Resources\Api\V1\Conversations\ConversationSummaryData;
use Domain\Agent\Models\Agent;
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
            ->whereHas('session', fn ($q) => $q->where('agent_id', $agent->id))
            ->onlyVisible()
            ->orderByLatestActivity()
            ->paginate($perPage)
            ->through(fn (Conversation $c): ConversationSummaryData => ConversationSummaryData::fromModel($c));
    }
}
