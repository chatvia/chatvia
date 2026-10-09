<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Actions\Permissions\SetMcpToolsScopePermissionAction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Support\Facades\Schema;

/**
 * SetMcpToolsScopePermissionAction is the "apply to the whole section" bulk
 * variant: it must flip every tool in the targeted scope (read OR write) to the
 * requested permission while leaving the other scope untouched, and it must
 * branch on storage shape exactly like the single-tool action — custom servers
 * write `mcp_tools`, preset servers write the `mcp_server_tool` membership.
 */
function smtsCustomServer(): AgentMcpServer
{
    $owner = User::factory()->create();

    return AgentMcpServer::create([
        'user_id' => $owner->id,
        'name' => 'Custom Server',
        'slug' => 'custom',
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
}

function smtsCustomTool(AgentMcpServer $server, string $name, McpToolScope $scope, McpToolPermission $permission): McpTool
{
    return McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => $name,
        'description' => 'desc',
        'input_schema' => ['type' => 'object', 'properties' => []],
        'scope' => $scope,
        'permission' => $permission,
        'fetched_at' => now(),
    ]);
}

it('flips every custom tool in the targeted scope and leaves the other scope alone', function (): void {
    $server = smtsCustomServer();
    $read1 = smtsCustomTool($server, 'list_a', McpToolScope::Read, McpToolPermission::Allow);
    $read2 = smtsCustomTool($server, 'list_b', McpToolScope::Read, McpToolPermission::Approval);
    $write = smtsCustomTool($server, 'delete_a', McpToolScope::Write, McpToolPermission::Allow);

    $result = app(SetMcpToolsScopePermissionAction::class)->execute($server, McpToolScope::Read, McpToolPermission::Blocked);

    expect($result->scope)->toBe(McpToolScope::Read)
        ->and($result->permission)->toBe(McpToolPermission::Blocked)
        ->and($result->updated)->toBe(2)
        ->and($read1->refresh()->permission)->toBe(McpToolPermission::Blocked)
        ->and($read2->refresh()->permission)->toBe(McpToolPermission::Blocked)
        ->and($write->refresh()->permission)->toBe(McpToolPermission::Allow);
});

it('reports zero updates when the scope has no tools', function (): void {
    $server = smtsCustomServer();
    smtsCustomTool($server, 'list_a', McpToolScope::Read, McpToolPermission::Allow);

    $result = app(SetMcpToolsScopePermissionAction::class)->execute($server, McpToolScope::Write, McpToolPermission::Blocked);

    expect($result->updated)->toBe(0);
});

it('does not touch tools belonging to a different server', function (): void {
    $server = smtsCustomServer();
    smtsCustomTool($server, 'list_a', McpToolScope::Read, McpToolPermission::Allow);

    $other = smtsCustomServer();
    $otherRead = smtsCustomTool($other, 'list_x', McpToolScope::Read, McpToolPermission::Allow);

    app(SetMcpToolsScopePermissionAction::class)->execute($server, McpToolScope::Read, McpToolPermission::Blocked);

    expect($otherRead->refresh()->permission)->toBe(McpToolPermission::Allow);
});

/**
 * Preset/builtin: the action flips the per-workspace `mcp_server_tool`
 * memberships for the scope, not any `mcp_tools` rows.
 */
it('flips the memberships for a preset connector scope', function (): void {
    $owner = User::factory()->create();
    $server = AgentMcpServer::create([
        'user_id' => $owner->id,
        'connector_slug' => 'leads',
        'uses_central_catalog' => true,
        'name' => 'Leads',
        'slug' => 'leads',
        'url' => 'https://example.test/mcp/leads',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    $read = ConnectorTool::factory()->create(['connector_slug' => 'leads', 'name' => 'list_leads', 'scope' => McpToolScope::Read]);
    $write = ConnectorTool::factory()->create(['connector_slug' => 'leads', 'name' => 'delete_lead', 'scope' => McpToolScope::Write]);
    McpServerTool::create(['mcp_server_id' => $server->id, 'connector_tool_id' => $read->id, 'permission' => McpToolPermission::Allow]);
    McpServerTool::create(['mcp_server_id' => $server->id, 'connector_tool_id' => $write->id, 'permission' => McpToolPermission::Allow]);

    $result = app(SetMcpToolsScopePermissionAction::class)->execute($server, McpToolScope::Read, McpToolPermission::Approval);

    expect($result->updated)->toBe(1)
        ->and(McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $read->id)->first()->permission)
        ->toBe(McpToolPermission::Approval)
        ->and(McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $write->id)->first()->permission)
        ->toBe(McpToolPermission::Allow);
})->skip(fn (): bool => ! Schema::hasTable('mcp_server_tool') || ! Schema::hasColumn('mcp_servers', 'uses_central_catalog'), 'schema out of date — run migrate:fresh.');
