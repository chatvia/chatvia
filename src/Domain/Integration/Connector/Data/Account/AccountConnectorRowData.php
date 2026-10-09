<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\Account;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Enums\ConnectorRowKind;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
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
        public ConnectorRowKind $kind,
        public ?ConnectorData $connector,
        public ?AgentMcpServerData $server,
    ) {}
}
