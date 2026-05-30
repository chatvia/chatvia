<?php

namespace Domain\Connectors\Actions;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Data\AccountConnectorRowData;
use Domain\Connectors\Data\AccountConnectorsResourceData;
use Domain\Connectors\Services\ConnectorCatalog;
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
        $servers = $user->mcpServers()->withCount('agents')->orderBy('created_at')->get();
        $serversByConnector = $servers->whereNotNull('connector_slug')->keyBy('connector_slug');

        $featured = $this->catalog->all()->values()->map(
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
