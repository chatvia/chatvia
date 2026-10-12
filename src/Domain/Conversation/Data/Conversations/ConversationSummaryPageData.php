<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Conversations;

use Domain\Conversation\Actions\Conversations\ListAgentConversationsAction;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * One keyset page of the API conversation list and the cursor of the next
 * page. Built by {@see ListAgentConversationsAction}.
 */
class ConversationSummaryPageData extends Data
{
    /**
     * @param  Collection<int, ConversationSummaryData>  $data
     * @param  string|null  $next_cursor  sent back as `cursor` for the next page; null on the last page
     */
    public function __construct(
        public Collection $data,
        public ?string $next_cursor,
    ) {}
}
