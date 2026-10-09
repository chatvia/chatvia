<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\Agent;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Enums\ConnectorRowState;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row on the agent's Connectors page — a catalog entry plus its state
 * (attached / unattached / available) and the provisioned MCP server when
 * one exists for the account.
 */
#[TypeScript]
class AgentConnectorRowData extends Data
{
    public function __construct(
        public ConnectorData $connector,
        public ConnectorRowState $state,
        public ?AgentMcpServerData $server,
        /** Attached AND marked "always on" for this agent: its tools ship on every turn. */
        public bool $always_available = false,
    ) {}
}
