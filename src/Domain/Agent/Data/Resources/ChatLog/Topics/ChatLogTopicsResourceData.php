<?php

namespace Domain\Agent\Data\Resources\ChatLog\Topics;

use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The topics the chat log can filter conversations by.
 */
#[TypeScript]
class ChatLogTopicsResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, ChatLogTopicData> */
        #[DataCollectionOf(ChatLogTopicData::class)]
        public DataCollection $topics,
    ) {}

    /**
     * @param  Collection<int, Topic>  $topics
     */
    public static function fromCollection(Collection $topics): self
    {
        return new self(
            topics: new DataCollection(
                ChatLogTopicData::class,
                $topics->map(fn (Topic $topic): ChatLogTopicData => ChatLogTopicData::fromTopic($topic)),
            ),
        );
    }
}
