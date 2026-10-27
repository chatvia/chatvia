<?php

namespace Domain\Integration\Connector\Queries;

use Domain\Integration\Connector\Data\Catalog\ConnectorToolData;
use Domain\Integration\Connector\Data\Catalog\ConnectorToolsResourceData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;

/**
 * Project a connector's tool catalog onto the shape the Connectors sheet
 * renders — tools prefixed with the server's namespace, plus the timestamp of
 * the last catalog refresh.
 *
 * Preset/builtin servers read the shared `connector_tools` catalog through the
 * `connector_tool_mcp_server` membership (permission from the membership, `id` is the
 * connector_tools.id the permission endpoint round-trips). Custom servers read
 * their own `mcp_tools` rows. Only the columns the sheet shows are read, not
 * the embedding or search text.
 */
class ConnectorToolsQuery
{
    public function get(AgentMcpServer $server): ConnectorToolsResourceData
    {
        $rows = $server->usesCentralCatalog()
            ? $this->presetRows($server)
            : $this->customRows($server);

        return new ConnectorToolsResourceData(
            tools: new DataCollection(ConnectorToolData::class, $rows),
            cached_at: $server->catalog_cached_at,
        );
    }

    /**
     * @return Collection<int, ConnectorToolData>
     */
    private function presetRows(AgentMcpServer $server): Collection
    {
        return $server->connectorTools()->orderBy('name')
            ->get(['connector_tools.id', 'connector_tools.name', 'connector_tools.description', 'connector_tools.input_schema', 'connector_tools.scope'])
            ->map(fn (ConnectorTool $tool): ConnectorToolData => new ConnectorToolData(
                id: $tool->id,
                name: $server->toolPrefix().$tool->name,
                short_name: $tool->name,
                description: $tool->description,
                parameters: $tool->input_schema,
                scope: $tool->scope,
                permission: $tool->pivot->permission,
            ));
    }

    /**
     * @return Collection<int, ConnectorToolData>
     */
    private function customRows(AgentMcpServer $server): Collection
    {
        return $server->tools()->orderBy('name')
            ->get(['id', 'name', 'description', 'input_schema', 'scope', 'permission'])
            ->map(fn (McpTool $tool): ConnectorToolData => new ConnectorToolData(
                id: $tool->id,
                name: $server->toolPrefix().$tool->name,
                short_name: $tool->name,
                description: $tool->description,
                parameters: $tool->input_schema,
                scope: $tool->scope,
                permission: $tool->permission,
            ));
    }
}
