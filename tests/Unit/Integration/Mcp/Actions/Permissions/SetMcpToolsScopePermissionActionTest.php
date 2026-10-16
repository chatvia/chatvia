<?php

use Domain\Integration\Mcp\Actions\Permissions\SetMcpToolsScopePermissionAction;
use Domain\Integration\Mcp\Data\McpScopePermissionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * SetMcpToolsScopePermissionAction is the "apply to the whole section" bulk
 * variant: it must flip every tool in the targeted scope (read OR write) to the
 * requested permission while leaving the other scope untouched, and it must
 * branch on storage shape exactly like the single-tool action — custom servers
 * write `mcp_tools`, preset servers write the `mcp_server_tool` membership.
 * The attached agents' cached tool pools are dropped only when a row changed.
 */
it('flips every custom tool in the targeted scope and leaves the other scope alone', function (): void {
    $registry = $this->spy(ToolRegistry::class);
    $server = AgentMcpServer::factory()->create();
    $read1 = McpTool::factory()->for($server, 'mcpServer')->read()->create();
    $read2 = McpTool::factory()->for($server, 'mcpServer')->create(['scope' => McpToolScope::Read, 'permission' => McpToolPermission::Approval]);
    $write = McpTool::factory()->for($server, 'mcpServer')->allowed()->create();

    $result = app(SetMcpToolsScopePermissionAction::class)->execute($server, new McpScopePermissionData(McpToolScope::Read, McpToolPermission::Blocked));

    expect($result->scope)->toBe(McpToolScope::Read)
        ->and($result->permission)->toBe(McpToolPermission::Blocked)
        ->and($result->updated)->toBe(2)
        ->and($read1->refresh()->permission)->toBe(McpToolPermission::Blocked)
        ->and($read2->refresh()->permission)->toBe(McpToolPermission::Blocked)
        ->and($write->refresh()->permission)->toBe(McpToolPermission::Allow);
    $registry->shouldHaveReceived('forgetAttachedTo')->once()->withArgs(fn (AgentMcpServer $forgotten): bool => $forgotten->is($server));
});

it('reports zero updates and keeps the cached pools when the scope has no tools', function (): void {
    $registry = $this->spy(ToolRegistry::class);
    $server = AgentMcpServer::factory()->create();
    McpTool::factory()->for($server, 'mcpServer')->read()->create();

    $result = app(SetMcpToolsScopePermissionAction::class)->execute($server, new McpScopePermissionData(McpToolScope::Write, McpToolPermission::Blocked));

    expect($result->updated)->toBe(0);
    $registry->shouldNotHaveReceived('forgetAttachedTo');
});

it('does not touch tools belonging to a different server', function (): void {
    $server = AgentMcpServer::factory()->create();
    McpTool::factory()->for($server, 'mcpServer')->read()->create();

    $otherRead = McpTool::factory()->read()->create();

    app(SetMcpToolsScopePermissionAction::class)->execute($server, new McpScopePermissionData(McpToolScope::Read, McpToolPermission::Blocked));

    expect($otherRead->refresh()->permission)->toBe(McpToolPermission::Allow);
});

/**
 * Preset/builtin: the action flips the per-workspace `mcp_server_tool`
 * memberships for the scope, not any `mcp_tools` rows.
 */
it('flips the memberships for a preset connector scope', function (): void {
    $registry = $this->spy(ToolRegistry::class);
    $server = AgentMcpServer::factory()->preset('leads')->create();

    $read = ConnectorTool::factory()->create(['connector_slug' => 'leads', 'name' => 'list_leads', 'scope' => McpToolScope::Read]);
    $write = ConnectorTool::factory()->create(['connector_slug' => 'leads', 'name' => 'delete_lead', 'scope' => McpToolScope::Write]);
    McpServerTool::create(['mcp_server_id' => $server->id, 'connector_tool_id' => $read->id, 'permission' => McpToolPermission::Allow]);
    McpServerTool::create(['mcp_server_id' => $server->id, 'connector_tool_id' => $write->id, 'permission' => McpToolPermission::Allow]);

    $result = app(SetMcpToolsScopePermissionAction::class)->execute($server, new McpScopePermissionData(McpToolScope::Read, McpToolPermission::Approval));

    expect($result->updated)->toBe(1)
        ->and(McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $read->id)->first()->permission)
        ->toBe(McpToolPermission::Approval)
        ->and(McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $write->id)->first()->permission)
        ->toBe(McpToolPermission::Allow);
    $registry->shouldHaveReceived('forgetAttachedTo')->once()->withArgs(fn (AgentMcpServer $forgotten): bool => $forgotten->is($server));
});
