<?php

namespace Domain\Integration\Connector\Data\Catalog;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Connector tool catalog payload — the list of tools plus when the cache was
 * last refreshed (drives the "refresh tools" affordance on the page).
 */
#[TypeScript]
class ConnectorToolsResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, ConnectorToolData> */
        #[DataCollectionOf(ConnectorToolData::class)]
        public DataCollection $tools,
        public ?CarbonInterface $cached_at,
    ) {}
}
