<?php

namespace Domain\Integration\Connector\Actions\Catalog;

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
 * `mcp_server_tool` membership (permission from the membership, `id` is the
 * connector_tools.id the permission endpoint round-trips). Custom servers read
 * their own `mcp_tools` rows.
 */
class ListConnectorToolsAction
{
    public function execute(AgentMcpServer $server): ConnectorToolsResourceData
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
        return $server->connectorTools()->orderBy('name')->get()
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
        return $server->tools()->orderBy('name')->get()
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
