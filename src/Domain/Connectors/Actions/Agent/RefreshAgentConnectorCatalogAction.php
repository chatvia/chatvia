<?php

namespace Domain\Connectors\Actions\Agent;

use Domain\Agents\Data\Resources\Mcp\AgentMcpServerData;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Domain\Connectors\Data\AgentConnectorRefreshResultData;
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
