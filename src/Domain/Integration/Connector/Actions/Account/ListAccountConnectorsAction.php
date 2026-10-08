<?php

namespace Domain\Integration\Connector\Actions\Account;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Account\AccountConnectorRowData;
use Domain\Integration\Connector\Data\Account\AccountConnectorsResourceData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Spatie\LaravelData\DataCollection;

/**
 * Assemble the Settings → Connectors page payload: the featured catalog rows
 * (each carrying any provisioned MCP server for the account) followed by the
 * account's custom MCP servers.
 */
class ListAccountConnectorsAction
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function execute(User $user): AccountConnectorsResourceData
    {
        // Connectors belong to the workspace owner; teammates see the same list.
        $servers = $user->workspace()->mcpServers()->withCount('agents')->orderBy('created_at')->get();
        $serversByConnector = $servers->whereNotNull('connector_slug')->keyBy('connector_slug');

        $featured = $this->catalog->connectors()->values()->map(
            fn ($connector): AccountConnectorRowData => new AccountConnectorRowData(
                kind: 'featured',
                connector: $connector,
                server: ($s = $serversByConnector->get($connector->slug)) ? AgentMcpServerData::fromModel($s) : null,
            ),
        );

        $custom = $servers->whereNull('connector_slug')->values()->map(
            fn (AgentMcpServer $server): AccountConnectorRowData => new AccountConnectorRowData(
                kind: 'custom',
                connector: null,
                server: AgentMcpServerData::fromModel($server),
            ),
        );

        return new AccountConnectorsResourceData(
            featured: new DataCollection(AccountConnectorRowData::class, $featured),
            custom: new DataCollection(AccountConnectorRowData::class, $custom),
        );
    }
}
