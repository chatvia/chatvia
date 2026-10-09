<?php

namespace Domain\Agent\Data\Resources\ChatLog\Messages;

use Domain\Agent\Data\Resources\ChatLog\Handoff\ChatLogHandoffStatusData;
use Domain\Agent\Data\Resources\ChatLog\Handoff\ChatLogLeadData;
use Domain\Conversation\Models\Message;
use Domain\Form\Models\FormSubmission;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One page of a conversation's messages in the chat log, with the
 * conversation's title, captured lead and handoff state.
 */
#[TypeScript]
class ChatLogMessagesResourceData extends Data
{
    /**
     * @param  LengthAwarePaginator<int, ChatLogMessageData>  $messages
     */
    public function __construct(
        public LengthAwarePaginator $messages,
        public ?string $conversation_title,
        public ?ChatLogLeadData $lead,
        public ChatLogHandoffStatusData $handoff,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, Message>  $messages  a page of messages; it is turned into a page of {@see ChatLogMessageData} in place
     */
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
            lead: $lead instanceof FormSubmission ? ChatLogLeadData::fromLead($lead) : null,
            handoff: $handoff,
        );
    }
}
