<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Actions\Catalog\ReconcileToolMembershipsAction;
use Domain\Integration\Mcp\Actions\Catalog\UpsertConnectorToolsAction;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;

/**
 * The two shared-catalog writes used by both the per-workspace sync and the
 * deploy-time builtin sync: catalog rows are stored once per connector, and
 * memberships follow the catalog without overwriting a workspace's choice.
 */
function catalogDefinitions(): array
{
    return McpToolDefinitionData::listFrom([
        ['name' => 'read_thing', 'description' => 'reads', 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_thing', 'description' => 'writes'],
        ['description' => 'no name — not a tool'],
    ]);
}

it('stores each tool once, with its scope and default permission', function (): void {
    $tools = app(UpsertConnectorToolsAction::class)->execute('weather', catalogDefinitions());
    app(UpsertConnectorToolsAction::class)->execute('weather', catalogDefinitions());

    expect($tools)->toHaveCount(2)
        ->and(ConnectorTool::where('connector_slug', 'weather')->count())->toBe(2)
        ->and($tools[0]->scope)->toBe(McpToolScope::Read)
        ->and($tools[0]->default_permission)->toBe(McpToolPermission::Allow)
        ->and($tools[1]->scope)->toBe(McpToolScope::Write)
        ->and($tools[1]->default_permission)->toBe(McpToolPermission::Approval)
        // A missing input schema becomes an empty object schema.
        ->and($tools[1]->input_schema)->toBe(['type' => 'object', 'properties' => []]);
});

it('never gates a first-party builtin, even its write tools', function (): void {
    $tools = app(UpsertConnectorToolsAction::class)->execute('forms', catalogDefinitions());

    expect($tools->pluck('default_permission')->unique()->all())->toBe([McpToolPermission::Allow]);
});

it('clears the embedding only when the description changes', function (): void {
    [$read] = app(UpsertConnectorToolsAction::class)->execute('weather', catalogDefinitions());
    ConnectorTool::whereKey($read->id)->update(['embedding_bm25' => null]);
    $read->forceFill(['embedding' => array_fill(0, 1024, 0.1)])->save();

    app(UpsertConnectorToolsAction::class)->execute('weather', catalogDefinitions());
    expect($read->fresh()->embedding)->not->toBeNull();

    app(UpsertConnectorToolsAction::class)->execute('weather', McpToolDefinitionData::listFrom([
        ['name' => 'read_thing', 'description' => 'reads, now worded differently', 'annotations' => ['readOnlyHint' => true]],
    ]));
    expect($read->fresh()->embedding)->toBeNull();
});

it('adds and drops memberships to match the catalog, keeping the workspace choice', function (): void {
    $server = AgentMcpServer::create([
        'user_id' => User::factory()->create()->id,
        'connector_slug' => 'weather',
        'name' => 'Weather',
        'slug' => 'weather',
        'url' => 'https://weather.example.test/mcp',
        'auth_type' => 'none',
    ]);
    $tools = app(UpsertConnectorToolsAction::class)->execute('weather', catalogDefinitions());

    app(ReconcileToolMembershipsAction::class)->execute($server, $tools);
    McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $tools[1]->id)
        ->update(['permission' => McpToolPermission::Blocked->value]);

    // Re-sync with the same catalog: the workspace's "blocked" survives.
    app(ReconcileToolMembershipsAction::class)->execute($server, $tools);
    expect(McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $tools[1]->id)->value('permission'))
        ->toBe(McpToolPermission::Blocked);

    // The server stops advertising the write tool: its membership goes.
    app(ReconcileToolMembershipsAction::class)->execute($server, $tools->take(1));
    expect(McpServerTool::where('mcp_server_id', $server->id)->pluck('connector_tool_id')->all())->toBe([$tools[0]->id]);

    // An empty catalog drops every membership.
    app(ReconcileToolMembershipsAction::class)->execute($server, $tools->take(0));
    expect(McpServerTool::where('mcp_server_id', $server->id)->count())->toBe(0);
});
