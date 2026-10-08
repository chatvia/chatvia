<?php

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Integration\Connector\Data\Agent\AgentConnectorRefreshResultData;
use Domain\Integration\Connector\Exceptions\ConnectorRefreshFailedException;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpCatalog;
use Throwable;

/**
 * Re-fetch the tool catalog for an account-level MCP server attached to an
 * agent. Any failure becomes a {@see ConnectorRefreshFailedException} the
 * controller surfaces as a 422.
 */
class RefreshAgentConnectorCatalogAction
{
    public function __construct(
        private readonly McpCatalog $catalog,
    ) {}

    public function execute(AgentMcpServer $server): AgentConnectorRefreshResultData
    {
        try {
            $tools = $this->catalog->refresh($server);
        } catch (Throwable $e) {
            throw ConnectorRefreshFailedException::because($e);
        }

        return new AgentConnectorRefreshResultData(
            data: AgentMcpServerData::fromModel($server->fresh()),
            tools_count: count($tools),
        );
    }
}
