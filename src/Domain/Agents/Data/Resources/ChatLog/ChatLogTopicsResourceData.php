<?php

namespace Domain\Agents\Data\Resources\ChatLog;

use Domain\Chat\Models\ChatTopic;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogTopicsResourceData extends Data
{
    public function __construct(
        #[DataCollectionOf(ChatLogTopicData::class)]
        public DataCollection $topics,
    ) {}

    public static function fromCollection(Collection $topics): self
    {
        return new self(
            topics: new DataCollection(
                ChatLogTopicData::class,
                $topics->map(fn (ChatTopic $topic): ChatLogTopicData => ChatLogTopicData::fromTopic($topic)),
            ),
        );
    }
}
