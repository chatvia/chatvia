<?php

namespace Domain\Integration\Connector\Data\Catalog;

use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Outcome of re-fetching a connector's tool catalog — the refreshed server
 * snapshot plus the new tool count. Same shape on the workspace Connectors
 * page and on an agent's Connectors page.
 */
#[TypeScript]
class ConnectorRefreshResultData extends Data
{
    public function __construct(
        public AgentMcpServerData $data,
        public int $tools_count,
    ) {}
}
