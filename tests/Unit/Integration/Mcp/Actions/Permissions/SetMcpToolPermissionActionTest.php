<?php

use Domain\Integration\Mcp\Actions\Permissions\SetMcpToolPermissionAction;
use Domain\Integration\Mcp\Data\McpToolPermissionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Exceptions\McpToolNotFoundException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * SetMcpToolPermissionAction writes to the tool-approval surface that gates
 * whether the LLM auto-executes a destructive write tool. It must store
 * exactly the requested permission, drop the cached tool pool of the agents
 * using the server (the pool leaves out blocked tools), and refuse to write
 * across server boundaries — a tool id that belongs to a different server
 * raises a McpToolNotFoundException so the controller can surface a 404.
 */
it('stores the requested permission on the tool row and drops the attached agents\' cached pools', function (McpToolPermission $from, McpToolPermission $to): void {
    $registry = $this->spy(ToolRegistry::class);
    $tool = McpTool::factory()->create(['permission' => $from]);
    $server = $tool->mcpServer;

    app(SetMcpToolPermissionAction::class)->execute($server, new McpToolPermissionData($tool->id, $to));

    expect($tool->refresh()->permission)->toBe($to);
    $registry->shouldHaveReceived('forgetAttachedTo')->once()->withArgs(fn (AgentMcpServer $forgotten): bool => $forgotten->is($server));
})->with([
    'allow to approval' => [McpToolPermission::Allow, McpToolPermission::Approval],
    'approval to blocked' => [McpToolPermission::Approval, McpToolPermission::Blocked],
    'blocked to allow' => [McpToolPermission::Blocked, McpToolPermission::Allow],
]);

it('throws McpToolNotFoundException for a tool id that belongs to a DIFFERENT server', function (): void {
    $server = AgentMcpServer::factory()->create();
    $otherTool = McpTool::factory()->create();

    expect(fn (): McpToolPermissionData => app(SetMcpToolPermissionAction::class)->execute($server, new McpToolPermissionData($otherTool->id, McpToolPermission::Blocked)))
        ->toThrow(McpToolNotFoundException::class);

    expect($otherTool->refresh()->permission)->not->toBe(McpToolPermission::Blocked);
});

/**
 * A preset/builtin server toggles the per-workspace `connector_tool_mcp_server` membership
 * (the tool id is a connector_tools.id), not its own `mcp_tools` row.
 *
 * @return array{0: AgentMcpServer, 1: ConnectorTool}
 */
function smtPresetServerWithTool(McpToolPermission $startPermission = McpToolPermission::Allow): array
{
    $server = AgentMcpServer::factory()->preset('leads')->create();
    $catalogTool = ConnectorTool::factory()->create(['connector_slug' => 'leads', 'name' => 'capture_lead']);
    $server->connectorTools()->attach($catalogTool->id, ['permission' => $startPermission->value]);

    return [$server, $catalogTool];
}

it('flips the membership permission for a preset connector tool', function (): void {
    [$server, $catalogTool] = smtPresetServerWithTool(McpToolPermission::Approval);
    $registry = $this->spy(ToolRegistry::class);

    $result = app(SetMcpToolPermissionAction::class)->execute($server, new McpToolPermissionData($catalogTool->id, McpToolPermission::Blocked));

    expect($result->id)->toBe($catalogTool->id)
        ->and($result->permission)->toBe(McpToolPermission::Blocked)
        ->and(McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $catalogTool->id)->first()->permission)
        ->toBe(McpToolPermission::Blocked);
    $registry->shouldHaveReceived('forgetAttachedTo')->once()->withArgs(fn (AgentMcpServer $forgotten): bool => $forgotten->is($server));
});

it('throws for a connector tool the preset server does not advertise (no membership)', function (): void {
    [$server] = smtPresetServerWithTool();
    $foreignCatalogTool = ConnectorTool::factory()->create(['connector_slug' => 'leads', 'name' => 'other_tool']);

    expect(fn (): McpToolPermissionData => app(SetMcpToolPermissionAction::class)->execute($server, new McpToolPermissionData($foreignCatalogTool->id, McpToolPermission::Blocked)))
        ->toThrow(McpToolNotFoundException::class);
});
