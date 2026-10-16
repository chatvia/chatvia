<?php

namespace Domain\Conversation\Topic\Data;

use Domain\Conversation\Topic\Models\Topic;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One topic row as the dashboard's topics endpoints return it. The dashboard
 * works with integer topic ids; `workspace_id` is null for the built-in
 * system topics.
 */
#[TypeScript]
class TopicRowData extends Data
{
    public function __construct(
        public int $id,
        public ?int $workspace_id,
        public string $topic,
        public string $description,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Topic $topic): self
    {
        return new self(
            id: $topic->id,
            workspace_id: $topic->workspace_id,
            topic: $topic->topic,
            description: $topic->description,
            created_at: $topic->created_at?->toIso8601String(),
            updated_at: $topic->updated_at?->toIso8601String(),
        );
    }
}
