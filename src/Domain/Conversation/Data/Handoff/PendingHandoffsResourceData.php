<?php

namespace Domain\Conversation\Data\Handoff;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Pending-handoff feed for an agent — the conversations waiting for a human
 * pickup plus the total count (the sidebar badge reads `count`).
 */
#[TypeScript]
class PendingHandoffsResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, HandoffSummaryData> */
        #[DataCollectionOf(HandoffSummaryData::class)]
        public DataCollection $pending,
        public int $count,
    ) {}
}
