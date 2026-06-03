<?php

namespace App\Http\Resources\Api\V1\Conversations;

use Domain\Conversation\Models\Message;
use Spatie\LaravelData\Data;

class ConversationMessageData extends Data
{
    public function __construct(
        public int $id,
        public ?string $role,
        public ?string $content,
        public ?string $created_at,
    ) {}

    public static function fromModel(Message $message): self
    {
        return new self(
            id: $message->id,
            role: $message->role,
            content: $message->content,
            created_at: $message->created_at?->toIso8601String(),
        );
    }
}
