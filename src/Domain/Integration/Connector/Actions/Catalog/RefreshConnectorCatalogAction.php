<?php

namespace Domain\Integration\Connector\Actions\Catalog;

use Domain\Integration\Connector\Data\Catalog\ConnectorRefreshResultData;
use Domain\Integration\Connector\Exceptions\ConnectorRefreshFailedException;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Throwable;

/**
 * The "Refresh tools" button of a connector, on the workspace Connectors page
 * and on an agent's Connectors page: re-sync the server's tool catalog. Any
 * failure becomes a {@see ConnectorRefreshFailedException} whose message is
 * safe to show.
 */
class RefreshConnectorCatalogAction
{
    public function __construct(
        private readonly SyncMcpServerCatalogAction $syncCatalog,
    ) {}

    public function execute(AgentMcpServer $server): ConnectorRefreshResultData
    {
        try {
            $tools = $this->syncCatalog->execute($server);
        } catch (Throwable $e) {
            throw ConnectorRefreshFailedException::because($e);
        }

        return new ConnectorRefreshResultData(
            data: AgentMcpServerData::fromModel($server->fresh()),
            tools_count: $tools->count(),
        );
    }
}
