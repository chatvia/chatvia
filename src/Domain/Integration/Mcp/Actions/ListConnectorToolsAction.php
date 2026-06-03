<?php

namespace Domain\Integration\Mcp\Actions;

use Domain\Integration\Connector\Data\Catalog\ConnectorToolData;
use Domain\Integration\Connector\Data\Catalog\ConnectorToolsResourceData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Spatie\LaravelData\DataCollection;

/**
 * Project a connector's stored MCP tool catalog onto the shape the Connectors
 * sheet renders — tools prefixed with the server's namespace, plus the
 * timestamp of the last catalog refresh.
 */
class ListConnectorToolsAction
{
    public function execute(AgentMcpServer $server): ConnectorToolsResourceData
    {
        $tools = $server->tools()->orderBy('name')->get();

        $rows = $tools->map(fn (McpTool $tool): ConnectorToolData => new ConnectorToolData(
            id: $tool->id,
            name: $server->toolPrefix().$tool->name,
            short_name: $tool->name,
            description: $tool->description,
            parameters: $tool->input_schema,
            is_enabled: (bool) $tool->is_enabled,
            requires_approval: (bool) $tool->requires_approval,
        ));

        return new ConnectorToolsResourceData(
            tools: new DataCollection(ConnectorToolData::class, $rows),
            cached_at: $server->catalog_cached_at,
        );
    }
}
