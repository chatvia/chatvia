<?php

namespace Domain\Agent\Data\Resources\ChatLog\Topics;

use Domain\Conversation\Topic\Models\Topic;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A topic the chat log can filter conversations by.
 */
#[TypeScript]
class ChatLogTopicData extends Data
{
    public function __construct(
        public int $id,
        public string $topic,
        public ?string $description,
    ) {}

    public static function fromTopic(Topic $topic): self
    {
        return new self(
            id: $topic->id,
            topic: $topic->topic,
            description: $topic->description,
        );
    }
}
