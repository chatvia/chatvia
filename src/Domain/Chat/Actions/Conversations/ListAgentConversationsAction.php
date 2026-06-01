<?php

namespace Domain\Chat\Actions\Conversations;

use App\Http\Resources\Api\V1\Conversations\ChatConversationSummaryData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Paginated conversation list for an agent — used by `GET
 * /api/v1/agents/{agent}/conversations`.
 */
class ListAgentConversationsAction
{
    /**
     * @return LengthAwarePaginator<int, ChatConversationSummaryData>
     */
    public function execute(Agent $agent, int $perPage): LengthAwarePaginator
    {
        return ChatConversation::query()
            ->whereHas('session', fn ($q) => $q->where('agent_id', $agent->id))
            ->onlyVisible()
            ->orderByLatestActivity()
            ->paginate($perPage)
            ->through(fn (ChatConversation $c): ChatConversationSummaryData => ChatConversationSummaryData::fromModel($c));
    }
}
