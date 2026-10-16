<?php

namespace Domain\Conversation\Data\Conversations;

use Domain\Conversation\Models\Conversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One hit of the chat sidebar's conversation search — the same
 * {id, title, timestamp} shape as the conversations index, so the search
 * dialog renders the results directly.
 */
#[TypeScript]
class ConversationSearchHitData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public int $timestamp,
    ) {}

    public static function fromModel(Conversation $conversation): self
    {
        return new self(
            id: $conversation->external_id,
            title: $conversation->title ?? Conversation::DEFAULT_TITLE,
            timestamp: $conversation->created_at?->timestamp ?? 0,
        );
    }
}
