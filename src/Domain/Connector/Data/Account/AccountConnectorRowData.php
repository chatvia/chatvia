<?php

namespace Domain\Connector\Data\Account;

use Domain\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Connector\Data\Catalog\ConnectorData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row on the Settings → Connectors page: either a featured catalog entry
 * (with an optional already-provisioned server) or a custom MCP server the
 * account added directly.
 */
#[TypeScript]
class AccountConnectorRowData extends Data
{
    public function __construct(
        /** @var "featured"|"custom" */
        public string $kind,
        public ?ConnectorData $connector,
        public ?AgentMcpServerData $server,
    ) {}
}
