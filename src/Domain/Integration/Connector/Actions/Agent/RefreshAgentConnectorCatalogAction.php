<?php

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Integration\Connector\Data\Agent\AgentConnectorRefreshResultData;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpCatalog;
use Domain\Integration\Mcp\Services\McpException;
use RuntimeException;
use Throwable;

/**
 * Re-fetch the tool catalog for an account-level MCP server attached to an
 * agent. Translates upstream MCP errors into a single `RuntimeException` the
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
            throw new RuntimeException(
                $e instanceof McpException ? $e->getMessage() : 'Could not refresh the connector.',
            );
        }

        return new AgentConnectorRefreshResultData(
            data: AgentMcpServerData::fromModel($server->fresh()),
            tools_count: count($tools),
        );
    }
}
