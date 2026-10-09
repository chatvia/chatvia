<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\Agent;

use Domain\Integration\Connector\Data\Custom\AgentCustomConnectorRowData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full payload for the agent's Connectors page: featured catalog entries
 * (with per-agent state) and custom MCP servers (with attachment flag).
 */
#[TypeScript]
class AgentConnectorsResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, AgentConnectorRowData> */
        #[DataCollectionOf(AgentConnectorRowData::class)]
        public DataCollection $featured,
        /** @var DataCollection<int, AgentCustomConnectorRowData> */
        #[DataCollectionOf(AgentCustomConnectorRowData::class)]
        public DataCollection $custom,
    ) {}
}
