<?php

namespace Domain\Conversation\Topic\Data;

use Domain\Conversation\Topic\Queries\TopicTrendsQuery;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The agent's topic charts: each busy topic's daily counts and the totals.
 * Built by {@see TopicTrendsQuery}.
 */
#[TypeScript]
class TopicsResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, TopicTrendData> */
        #[DataCollectionOf(TopicTrendData::class)]
        public DataCollection $topics_by_date,
        /** @var DataCollection<int, TopicSummaryData> */
        #[DataCollectionOf(TopicSummaryData::class)]
        public DataCollection $total_topics,
        public int $total_count,
    ) {}
}
