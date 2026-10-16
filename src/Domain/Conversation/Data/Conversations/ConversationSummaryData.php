<?php

namespace Domain\Conversation\Data\Conversations;

use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;

/**
 * Lightweight conversation projection for the public API's list and create
 * endpoints. Lives in the Domain layer (not Resources) because the list
 * Action builds it, and Domain cannot import App.
 */
class ConversationSummaryData extends Data
{
    public function __construct(
        /**
         * The conversation's ID.
         */
        public string $id,
        /**
         * The conversation's title. Auto-generated after the first reply
         * unless one was set.
         */
        public ?string $title,
        /**
         * The analyzed visitor sentiment: `positive`, `neutral` or
         * `negative`. Null until the conversation is analyzed.
         */
        public ?Sentiment $sentiment,
        /**
         * The analyzed conversation tone. Null until analyzed.
         */
        public ?Tone $tone,
        /**
         * Who answers: `ai`, `pending` (handoff requested), `human`
         * (an operator took over) or `returned`.
         */
        public ?HandoffStatus $handoff_status,
        /**
         * When the conversation last saw activity.
         */
        public ?string $last_activity_at,
        /**
         * When the conversation was opened.
         */
        public ?string $created_at,
    ) {}

    public static function fromModel(Conversation $conversation): self
    {
        return new self(
            id: $conversation->external_id,
            title: $conversation->title,
            sentiment: $conversation->sentiment,
            tone: $conversation->tone,
            handoff_status: $conversation->handoff_status,
            last_activity_at: $conversation->last_activity_at?->toIso8601String(),
            created_at: $conversation->created_at?->toIso8601String(),
        );
    }
}
