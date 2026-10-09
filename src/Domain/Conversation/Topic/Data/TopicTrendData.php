<?php

namespace Domain\Conversation\Topic\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One topic's conversation count for each day of the chart range.
 */
#[TypeScript]
class TopicTrendData extends Data
{
    public function __construct(
        public string $topic_name,
        /** @var DataCollection<int, TopicCountByDateData> */
        #[DataCollectionOf(TopicCountByDateData::class)]
        public DataCollection $counts,
    ) {}
}
