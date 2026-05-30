<?php

namespace Domain\Connectors\Actions;

use Domain\Agents\Models\AgentMcpServer;
use Domain\Agents\Models\McpTool;
use Domain\Connectors\Data\ConnectorToolData;
use Domain\Connectors\Data\ConnectorToolsResourceData;
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
            enabled: (bool) $tool->enabled,
        ));

        return new ConnectorToolsResourceData(
            tools: new DataCollection(ConnectorToolData::class, $rows),
            cached_at: $server->catalog_cached_at,
        );
    }
}
