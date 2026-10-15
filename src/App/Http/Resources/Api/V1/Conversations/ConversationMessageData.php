<?php

namespace App\Http\Resources\Api\V1\Conversations;

use Domain\Conversation\Models\Message;
use Spatie\LaravelData\Data;

/**
 * One message of a conversation in the public API (nested in
 * {@see ConversationDetailData}): who wrote it, the text and when.
 */
class ConversationMessageData extends Data
{
    public function __construct(
        /**
         * The message's ID.
         */
        public string $id,
        /**
         * Who wrote the message: `user` (the visitor), `assistant` (the AI
         * agent) or `agent` (a human operator who took over the chat).
         */
        public ?string $role,
        /**
         * The message text.
         */
        public ?string $content,
        /**
         * When the message was sent.
         */
        public ?string $created_at,
    ) {}

    public static function fromModel(Message $message): self
    {
        return new self(
            id: $message->external_id,
            role: $message->role->value,
            content: $message->content,
            created_at: $message->created_at?->toIso8601String(),
        );
    }
}
