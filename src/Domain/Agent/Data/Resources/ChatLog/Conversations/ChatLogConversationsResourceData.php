<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\ChatLog\Conversations;

use Domain\Agent\Actions\ChatLog\ListChatLogConversationsAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One page of an agent's chat log and the ids of the page's conversations that
 * captured a lead. Built by {@see ListChatLogConversationsAction}.
 */
#[TypeScript]
class ChatLogConversationsResourceData extends Data
{
    /**
     * @param  Collection<int, int>  $lead_conversation_ids  internal conversation ids (chat_conversations PK)
     */
    public function __construct(
        public LengthAwarePaginator $conversations,
        public Collection $lead_conversation_ids,
    ) {}
}
