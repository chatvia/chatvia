<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Catalog\ListConnectorToolsAction;
use Domain\Integration\Connector\Data\Catalog\ConnectorToolData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;

/**
 * The Connectors sheet lists a server's tools by name, each prefixed with the
 * server's tool namespace. A custom server reads its own `mcp_tools` rows; a
 * preset server reads the shared catalog through its membership rows, which
 * carry this workspace's permission.
 */
function toolListServer(bool $usesCentralCatalog, string $slug): AgentMcpServer
{
    return AgentMcpServer::create([
        'user_id' => User::factory()->create()->id,
        'connector_slug' => $usesCentralCatalog ? $slug : null,
        'uses_central_catalog' => $usesCentralCatalog,
        'name' => ucfirst($slug),
        'slug' => $slug,
        'url' => 'https://mcp.example.test/'.$slug,
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
        'catalog_cached_at' => now()->subHour(),
    ]);
}

function customToolOf(AgentMcpServer $server, string $name, McpToolPermission $permission): McpTool
{
    return McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => $name,
        'description' => 'Tool '.$name,
        'input_schema' => ['type' => 'object', 'properties' => []],
        'scope' => McpToolScope::Read,
        'permission' => $permission,
        'fetched_at' => now(),
    ]);
}

it('lists a custom server\'s own tools by name with the namespace prefix', function (): void {
    $server = toolListServer(false, 'acme');
    $zeta = customToolOf($server, 'zeta_search', McpToolPermission::Approval);
    $alpha = customToolOf($server, 'alpha_lookup', McpToolPermission::Allow);
    customToolOf(toolListServer(false, 'other'), 'foreign_tool', McpToolPermission::Allow);

    $result = app(ListConnectorToolsAction::class)->execute($server->fresh());

    $tools = $result->tools->toCollection();
    expect($tools->map(fn (ConnectorToolData $tool): array => [$tool->id, $tool->short_name, $tool->name, $tool->permission])->all())
        ->toBe([
            [$alpha->id, 'alpha_lookup', $server->toolPrefix().'alpha_lookup', McpToolPermission::Allow],
            [$zeta->id, 'zeta_search', $server->toolPrefix().'zeta_search', McpToolPermission::Approval],
        ])
        ->and($result->cached_at?->toIso8601String())->toBe($server->fresh()->catalog_cached_at->toIso8601String());
});

it('lists a preset server\'s catalog tools with the workspace\'s permission', function (): void {
    $server = toolListServer(true, 'presetco');
    $write = ConnectorTool::factory()->create(['connector_slug' => 'presetco', 'name' => 'create_item', 'scope' => McpToolScope::Write]);
    $read = ConnectorTool::factory()->create(['connector_slug' => 'presetco', 'name' => 'browse_items', 'scope' => McpToolScope::Read]);
    // In the shared catalog but not a member of this server: never listed.
    ConnectorTool::factory()->create(['connector_slug' => 'presetco', 'name' => 'hidden_tool']);
    McpServerTool::create(['mcp_server_id' => $server->id, 'connector_tool_id' => $write->id, 'permission' => McpToolPermission::Blocked]);
    McpServerTool::create(['mcp_server_id' => $server->id, 'connector_tool_id' => $read->id, 'permission' => McpToolPermission::Allow]);

    $tools = app(ListConnectorToolsAction::class)->execute($server->fresh())->tools->toCollection();

    expect($tools->map(fn (ConnectorToolData $tool): array => [$tool->id, $tool->name, $tool->scope, $tool->permission])->all())
        ->toBe([
            [$read->id, $server->toolPrefix().'browse_items', McpToolScope::Read, McpToolPermission::Allow],
            [$write->id, $server->toolPrefix().'create_item', McpToolScope::Write, McpToolPermission::Blocked],
        ]);
});
