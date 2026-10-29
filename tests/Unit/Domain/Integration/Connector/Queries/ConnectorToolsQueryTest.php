<?php

use Domain\Integration\Connector\Data\Catalog\ConnectorToolData;
use Domain\Integration\Connector\Queries\ConnectorToolsQuery;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;

/**
 * The Connectors sheet lists a server's tools by name, each prefixed with the
 * server's tool namespace. A preset server reads the shared catalog through
 * its membership rows, which carry this workspace's permission; a catalog
 * tool the server is not a member of is never listed.
 */
it('lists a preset server\'s catalog tools with the workspace\'s permission', function (): void {
    $server = AgentMcpServer::factory()->preset('presetco')->create(['catalog_cached_at' => now()->subHour()]);
    $write = ConnectorTool::factory()->create(['connector_slug' => 'presetco', 'name' => 'create_item', 'scope' => McpToolScope::Write]);
    $read = ConnectorTool::factory()->create(['connector_slug' => 'presetco', 'name' => 'browse_items', 'scope' => McpToolScope::Read]);
    ConnectorTool::factory()->create(['connector_slug' => 'presetco', 'name' => 'hidden_tool']);
    McpServerTool::create(['mcp_server_id' => $server->id, 'connector_tool_id' => $write->id, 'permission' => McpToolPermission::Blocked]);
    McpServerTool::create(['mcp_server_id' => $server->id, 'connector_tool_id' => $read->id, 'permission' => McpToolPermission::Allow]);

    $tools = app(ConnectorToolsQuery::class)->get($server->fresh())->tools->toCollection();

    expect($tools->map(fn (ConnectorToolData $tool): array => [$tool->id, $tool->name, $tool->scope, $tool->permission])->all())
        ->toBe([
            [$read->id, $server->toolPrefix().'browse_items', McpToolScope::Read, McpToolPermission::Allow],
            [$write->id, $server->toolPrefix().'create_item', McpToolScope::Write, McpToolPermission::Blocked],
        ]);
});
