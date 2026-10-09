<?php

namespace Domain\Integration\Mcp\Data\Catalog;

/**
 * What the deploy-time builtin sync did: the connectors it catalogued and how
 * many agents had their cached tool pool dropped.
 */
final readonly class BuiltinCatalogSyncResultData
{
    /**
     * @param  list<SyncedBuiltinConnectorData>  $connectors
     */
    public function __construct(
        public array $connectors,
        public int $flushedAgents,
    ) {}
}
