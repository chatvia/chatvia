<?php

declare(strict_types=1);

namespace Domain\Security\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full payload of an agent's Blocked list: the workspace's visitor blocks in
 * force (a block covers every agent) and the switched-off chatters who have
 * access to the agent.
 */
#[TypeScript]
class BlockListResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, BlockedVisitorRowData> */
        #[DataCollectionOf(BlockedVisitorRowData::class)]
        public DataCollection $visitors,
        /** @var DataCollection<int, DeactivatedChatterRowData> */
        #[DataCollectionOf(DeactivatedChatterRowData::class)]
        public DataCollection $chatters,
    ) {}
}
