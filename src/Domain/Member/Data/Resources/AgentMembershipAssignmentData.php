<?php

namespace Domain\Member\Data\Resources;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Result shape for the connector/function attach sheets: every account-level
 * agent split into "currently attached" vs "available to attach" buckets.
 */
#[TypeScript]
class AgentMembershipAssignmentData extends Data
{
    public function __construct(
        /** @var DataCollection<int, AgentMembershipRowData> */
        #[DataCollectionOf(AgentMembershipRowData::class)]
        public DataCollection $attached,
        /** @var DataCollection<int, AgentMembershipRowData> */
        #[DataCollectionOf(AgentMembershipRowData::class)]
        public DataCollection $available,
    ) {}
}
