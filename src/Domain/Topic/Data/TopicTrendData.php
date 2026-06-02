<?php

namespace Domain\Topic\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TopicTrendData extends Data
{
    public function __construct(
        public string $topic_name,
        #[DataCollectionOf(TopicCountByDateData::class)]
        public DataCollection $counts,
    ) {}
}
