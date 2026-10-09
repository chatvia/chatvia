<?php

namespace Domain\Conversation\Data\Conversations;

use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;

/**
 * Lightweight conversation projection for the API list endpoint.
 *
 * Sentiment + tone are plan-gated by the conversation's team plan — Starter
 * teams see null even if the backfill happened on a previous Pro subscription.
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

    public static function fromModel(Conversation $conversation, bool $sentimentEnabled, bool $toneEnabled): self
    {
        return new self(
            id: $conversation->external_id,
            title: $conversation->title,
            // Plan-gated. The caller resolves the two capability flags ONCE per
            // agent (every row in a list shares the same agent) rather than
            // walking $conversation->agent->team per row (an N+1 over the page).
            sentiment: $sentimentEnabled ? $conversation->sentiment : null,
            tone: $toneEnabled ? $conversation->tone : null,
            handoff_status: $conversation->handoff_status?->value,
            last_activity_at: $conversation->last_activity_at?->toIso8601String(),
            created_at: $conversation->created_at?->toIso8601String(),
        );
    }
}
