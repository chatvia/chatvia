<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Actions\Catalog\ReconcileToolMembershipsAction;
use Domain\Integration\Mcp\Actions\Catalog\UpsertConnectorToolsAction;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpServerTool;

/**
 * A workspace server's memberships of the shared catalog follow the catalog:
 * new tools join with the catalog default (reads run, writes wait), a tool
 * the server stops advertising leaves, an empty catalog drops them all, and
 * the workspace's own permission choice survives a re-sync.
 */

/**
 * A read tool, a write tool and an entry with no name (dropped on the way in).
 *
 * @return list<McpToolDefinitionData>
 */
function rtmDefinitions(): array
{
    return McpToolDefinitionData::listFrom([
        ['name' => 'read_thing', 'description' => 'reads', 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_thing', 'description' => 'writes'],
        ['description' => 'no name — not a tool'],
    ]);
}

it('adds and drops memberships to match the catalog, keeping the workspace choice', function (): void {
    $server = AgentMcpServer::factory()->preset('weather')->create();
    $tools = app(UpsertConnectorToolsAction::class)->execute('weather', rtmDefinitions());

    app(ReconcileToolMembershipsAction::class)->execute($server, $tools);
    expect(McpServerTool::where('mcp_server_id', $server->id)->orderBy('connector_tool_id')->pluck('permission')->all())
        ->toBe([McpToolPermission::Allow, McpToolPermission::Approval]);
    McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $tools[1]->id)
        ->update(['permission' => McpToolPermission::Blocked->value]);

    app(ReconcileToolMembershipsAction::class)->execute($server, $tools);
    expect(McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $tools[1]->id)->value('permission'))
        ->toBe(McpToolPermission::Blocked);

    app(ReconcileToolMembershipsAction::class)->execute($server, $tools->take(1));
    expect(McpServerTool::where('mcp_server_id', $server->id)->pluck('connector_tool_id')->all())->toBe([$tools[0]->id]);

    app(ReconcileToolMembershipsAction::class)->execute($server, $tools->take(0));
    expect(McpServerTool::where('mcp_server_id', $server->id)->count())->toBe(0);
});
