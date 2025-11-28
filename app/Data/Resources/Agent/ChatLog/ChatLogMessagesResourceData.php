<?php

namespace App\Data\Resources\Agent\ChatLog;

use App\Models\ChatMessage;
use App\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogMessagesResourceData extends Data
{
    public function __construct(
        public LengthAwarePaginator $messages,
        public ?string $conversation_title,
        public ?ChatLogLeadData $lead,
    ) {}

    public static function fromPaginator(LengthAwarePaginator $messages, ?Lead $lead = null): self
    {
        $conversationTitle = null;
        if ($messages->isNotEmpty()) {
            $firstMessage = $messages->first();
            if ($firstMessage instanceof ChatMessage) {
                $conversationTitle = str($firstMessage->content)->stripTags()->limit(80)->value();
            }
        }

        $messages->through(fn (ChatMessage $message) => ChatLogMessageData::fromMessage($message));

        return new self(
            messages: $messages,
            conversation_title: $conversationTitle,
            lead: $lead ? ChatLogLeadData::fromLead($lead) : null,
        );
    }
}
