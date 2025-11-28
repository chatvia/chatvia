<?php

namespace App\Data\Resources\Agent\ChatLog;

use App\Models\ChatConversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogConversationsResourceData extends Data
{
    /**
     * @param  Collection<int, string>  $lead_conversation_ids
     */
    public function __construct(
        public LengthAwarePaginator $conversations,
        public Collection $lead_conversation_ids,
    ) {}

    /**
     * @param  Collection<string, string>  $excerpts
     * @param  Collection<int, string>  $leadConversationIds
     */
    public static function fromPaginator(
        LengthAwarePaginator $conversations,
        Collection $excerpts,
        Collection $leadConversationIds,
    ): self {
        $conversations->through(
            fn (ChatConversation $conversation) => ChatLogConversationData::fromConversation(
                $conversation,
                $excerpts->get($conversation->id)
            )
        );

        return new self(
            conversations: $conversations,
            lead_conversation_ids: $leadConversationIds,
        );
    }
}
