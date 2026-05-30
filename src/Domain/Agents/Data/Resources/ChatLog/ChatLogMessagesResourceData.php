<?php

namespace Domain\Agents\Data\Resources\ChatLog;

use Domain\Agents\Models\Lead;
use Domain\Chat\Models\ChatMessage;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogMessagesResourceData extends Data
{
    public function __construct(
        public LengthAwarePaginator $messages,
        public ?string $conversation_title,
        public ?ChatLogLeadData $lead,
        public ChatLogHandoffStatusData $handoff,
    ) {}

    public static function fromPaginator(
        LengthAwarePaginator $messages,
        ChatLogHandoffStatusData $handoff,
        ?Lead $lead = null,
    ): self {
        $conversationTitle = null;
        if ($messages->isNotEmpty()) {
            $firstMessage = $messages->first();
            if ($firstMessage instanceof ChatMessage) {
                $conversationTitle = str($firstMessage->content)->stripTags()->limit(80)->value();
            }
        }

        $messages->through(fn (ChatMessage $message): ChatLogMessageData => ChatLogMessageData::fromMessage($message));

        return new self(
            messages: $messages,
            conversation_title: $conversationTitle,
            lead: $lead ? ChatLogLeadData::fromLead($lead) : null,
            handoff: $handoff,
        );
    }
}
