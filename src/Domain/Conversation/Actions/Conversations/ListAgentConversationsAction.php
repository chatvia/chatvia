<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Conversations\ConversationSummaryData;
use Domain\Conversation\Data\Conversations\ConversationSummaryPageData;
use Domain\Conversation\Models\Conversation;

/**
 * One page of an agent's visible conversations, newest activity first — used
 * by `GET /api/v1/agents/{agent}/conversations`. Keyset-paged: the result's
 * `next_cursor` fetches the next page, so a deep page costs the same as the
 * first.
 */
class ListAgentConversationsAction
{
    public function execute(Agent $agent, int $perPage, ?string $cursor = null): ConversationSummaryPageData
    {
        $page = Conversation::query()
            ->forAgent($agent->id)
            ->onlyVisible()
            ->latestActivityPage($perPage, $cursor);

        return new ConversationSummaryPageData(
            data: collect($page->items())
                ->map(fn (Conversation $conversation): ConversationSummaryData => ConversationSummaryData::fromModel($conversation))
                ->values(),
            // Read from the models: the cursor holds their (updated_at, id).
            next_cursor: $page->nextCursor()?->encode(),
        );
    }
}
