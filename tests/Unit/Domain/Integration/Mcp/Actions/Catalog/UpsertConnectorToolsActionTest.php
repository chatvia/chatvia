<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Actions\Catalog\ReconcileToolMembershipsAction;
use Domain\Integration\Mcp\Actions\Catalog\UpsertConnectorToolsAction;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;

/**
 * The shared-catalog write used by both the per-workspace sync and the
 * deploy-time builtin sync: catalog rows are stored once per connector, with
 * a scope and default permission from the tool's hints (a missing input
 * schema becomes an empty object schema; a first-party builtin is never
 * gated). A tool whose wording changed waits for approval again in every
 * workspace, unless the workspace blocked it.
 */

/**
 * A read tool, a write tool and an entry with no name (dropped on the way in).
 *
 * @return list<McpToolDefinitionData>
 */
function uctDefinitions(): array
{
    return McpToolDefinitionData::listFrom([
        ['name' => 'read_thing', 'description' => 'reads', 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_thing', 'description' => 'writes'],
        ['description' => 'no name — not a tool'],
    ]);
}

it('stores each tool once with its scope and default permission, never gating a first-party builtin', function (): void {
    $tools = app(UpsertConnectorToolsAction::class)->execute('weather', uctDefinitions());
    app(UpsertConnectorToolsAction::class)->execute('weather', uctDefinitions());
    $builtin = app(UpsertConnectorToolsAction::class)->execute('forms', uctDefinitions());

    expect($tools)->toHaveCount(2)
        ->and(ConnectorTool::where('connector_slug', 'weather')->count())->toBe(2)
        ->and($tools[0]->scope)->toBe(McpToolScope::Read)
        ->and($tools[0]->default_permission)->toBe(McpToolPermission::Allow)
        ->and($tools[1]->scope)->toBe(McpToolScope::Write)
        ->and($tools[1]->default_permission)->toBe(McpToolPermission::Approval)
        ->and($tools[1]->input_schema)->toBe(['type' => 'object', 'properties' => []])
        ->and($builtin->pluck('default_permission')->unique()->all())->toBe([McpToolPermission::Allow]);
});

it('asks every workspace again once a tool changed, but keeps a block', function (): void {
    $server = AgentMcpServer::factory()->preset('weather')->create();
    $tools = app(UpsertConnectorToolsAction::class)->execute('weather', uctDefinitions());
    app(ReconcileToolMembershipsAction::class)->execute($server, $tools);
    $permission = fn (int $tool): McpToolPermission => McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $tools[$tool]->id)->sole()->permission;
    McpServerTool::where('mcp_server_id', $server->id)->update(['permission' => McpToolPermission::Allow->value]);

    app(UpsertConnectorToolsAction::class)->execute('weather', McpToolDefinitionData::listFrom([
        ['name' => 'read_thing', 'description' => 'reads', 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_thing', 'description' => 'writes and deletes'],
    ]));
    expect($permission(0))->toBe(McpToolPermission::Allow)
        ->and($permission(1))->toBe(McpToolPermission::Approval);

    McpServerTool::where('mcp_server_id', $server->id)->update(['permission' => McpToolPermission::Blocked->value]);
    app(UpsertConnectorToolsAction::class)->execute('weather', McpToolDefinitionData::listFrom([
        ['name' => 'write_thing', 'description' => 'writes, deletes and more'],
    ]));
    expect($permission(1))->toBe(McpToolPermission::Blocked);
});
