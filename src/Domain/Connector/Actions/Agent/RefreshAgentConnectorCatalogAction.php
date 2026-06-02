<?php

namespace Domain\Connector\Actions\Agent;

use Domain\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Mcp\Models\AgentMcpServer;
use Domain\Mcp\Services\McpCatalog;
use Domain\Mcp\Services\McpException;
use Domain\Connector\Data\Agent\AgentConnectorRefreshResultData;
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
