<?php

namespace App\Http\Resources\Api\V1;

use Domain\Chat\Models\ChatMessage;
use Spatie\LaravelData\Data;

class ChatConversationMessageData extends Data
{
    public function __construct(
        public int $id,
        public ?string $role,
        public ?string $content,
        public ?string $created_at,
    ) {}

    public static function fromModel(ChatMessage $message): self
    {
        return new self(
            id: $message->id,
            role: $message->role,
            content: $message->content,
            created_at: $message->created_at?->toIso8601String(),
        );
    }
}
