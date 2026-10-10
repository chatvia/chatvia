<?php

namespace Domain\Conversation\Data\Conversations;

use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;

/**
 * Lightweight conversation projection for the API list endpoint.
 */
class ConversationSummaryData extends Data
{
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $sentiment,
        public ?string $tone,
        public ?string $handoff_status,
        public ?string $last_activity_at,
        public ?string $created_at,
    ) {}

    public static function fromModel(Conversation $conversation): self
    {
        return new self(
            id: $conversation->external_id,
            title: $conversation->title,
            sentiment: $conversation->sentiment,
            tone: $conversation->tone,
            handoff_status: $conversation->handoff_status?->value,
            last_activity_at: $conversation->last_activity_at?->toIso8601String(),
            created_at: $conversation->created_at?->toIso8601String(),
        );
    }
}
