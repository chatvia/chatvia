<?php

namespace Domain\Agent\Data\Resources\ChatLog\Messages;

use Domain\Agent\Data\Resources\ChatLog\Handoff\ChatLogHandoffStatusData;
use Domain\Agent\Data\Resources\ChatLog\Handoff\ChatLogLeadData;
use Domain\Conversation\Models\Message;
use Domain\Form\Models\FormSubmission;
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
        ?FormSubmission $lead = null,
    ): self {
        $conversationTitle = null;
        if ($messages->isNotEmpty()) {
            $firstMessage = $messages->first();
            if ($firstMessage instanceof Message) {
                $conversationTitle = str($firstMessage->content)->stripTags()->limit(80)->value();
            }
        }

        $messages->through(fn (Message $message): ChatLogMessageData => ChatLogMessageData::fromMessage($message));

        return new self(
            messages: $messages,
            conversation_title: $conversationTitle,
            lead: $lead ? ChatLogLeadData::fromLead($lead) : null,
            handoff: $handoff,
        );
    }
}
