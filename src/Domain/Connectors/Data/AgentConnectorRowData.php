<?php

namespace Domain\Connectors\Data;

use Domain\Agents\Data\Resources\AgentMcpServerData;
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
        /** @var "attached"|"unattached"|"available" */
        public string $state,
        public ?AgentMcpServerData $server,
    ) {}
}
