<?php

namespace Domain\Agents\Data\Resources\ChatLog\Topics;

use Domain\Chat\Models\ChatTopic;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogTopicData extends Data
{
    public function __construct(
        public int $id,
        public string $topic,
        public ?string $description,
    ) {}

    public static function fromTopic(ChatTopic $topic): self
    {
        return new self(
            id: $topic->id,
            topic: $topic->topic,
            description: $topic->description,
        );
    }
}
