<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Actions\SetMcpToolPermissionAction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Exceptions\McpToolNotFoundException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Support\Facades\Schema;

/**
 * SetMcpToolPermissionAction writes to the tool-approval surface that gates
 * whether the LLM auto-executes a destructive write tool. The three-state
 * transition (Allow → Approval → Blocked, and any other combo) must persist
 * exactly the requested state, and the action must refuse to write across
 * server boundaries — a tool id that belongs to a different server raises
 * a McpToolNotFoundException so the controller can surface a 404.
 */
function smtServerWithTool(McpToolPermission $startPermission = McpToolPermission::Allow): array
{
    $owner = User::factory()->create();
    $server = AgentMcpServer::create([
        'user_id' => $owner->id,
        'name' => 'Test Server',
        'slug' => 'test',
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    $tool = McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => 'do_thing',
        'description' => 'Does a thing',
        'input_schema' => ['type' => 'object', 'properties' => []],
        'permission' => $startPermission,
        'fetched_at' => now(),
    ]);

    return [$server, $tool];
}

it('transitions Allow → Approval', function (): void {
    [$server, $tool] = smtServerWithTool(McpToolPermission::Allow);

    $result = app(SetMcpToolPermissionAction::class)->execute($server, $tool->id, McpToolPermission::Approval);

    expect($result->permission)->toBe(McpToolPermission::Approval)
        ->and($tool->refresh()->permission)->toBe(McpToolPermission::Approval);
});

it('transitions Approval → Blocked', function (): void {
    [$server, $tool] = smtServerWithTool(McpToolPermission::Approval);

    $result = app(SetMcpToolPermissionAction::class)->execute($server, $tool->id, McpToolPermission::Blocked);

    expect($result->permission)->toBe(McpToolPermission::Blocked)
        ->and($tool->refresh()->permission)->toBe(McpToolPermission::Blocked);
});

it('transitions Blocked → Allow', function (): void {
    [$server, $tool] = smtServerWithTool(McpToolPermission::Blocked);

    $result = app(SetMcpToolPermissionAction::class)->execute($server, $tool->id, McpToolPermission::Allow);

    expect($result->permission)->toBe(McpToolPermission::Allow);
});

it('throws McpToolNotFoundException for a tool id that belongs to a DIFFERENT server', function (): void {
    [$server] = smtServerWithTool();
    [, $otherTool] = smtServerWithTool();

    expect(fn () => app(SetMcpToolPermissionAction::class)->execute($server, $otherTool->id, McpToolPermission::Blocked))
        ->toThrow(McpToolNotFoundException::class);

    expect($otherTool->refresh()->permission)->not->toBe(McpToolPermission::Blocked);
});

/**
 * A preset/builtin server toggles the per-workspace `mcp_server_tool` membership
 * (the tool id is a connector_tools.id), not its own `mcp_tools` row.
 */
function smtPresetServerWithTool(McpToolPermission $startPermission = McpToolPermission::Allow): array
{
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

    $catalogTool = ConnectorTool::factory()->create(['connector_slug' => 'leads', 'name' => 'capture_lead']);

    McpServerTool::create([
        'mcp_server_id' => $server->id,
        'connector_tool_id' => $catalogTool->id,
        'permission' => $startPermission,
    ]);

    return [$server, $catalogTool];
}

it('flips the membership permission for a preset connector tool', function (): void {
    [$server, $catalogTool] = smtPresetServerWithTool(McpToolPermission::Approval);

    $result = app(SetMcpToolPermissionAction::class)->execute($server, $catalogTool->id, McpToolPermission::Blocked);

    expect($result->id)->toBe($catalogTool->id)
        ->and($result->permission)->toBe(McpToolPermission::Blocked)
        ->and(McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $catalogTool->id)->first()->permission)
        ->toBe(McpToolPermission::Blocked);
})->skip(fn (): bool => ! Schema::hasTable('mcp_server_tool') || ! Schema::hasColumn('mcp_servers', 'uses_central_catalog'), 'schema out of date — run migrate:fresh.');

it('throws for a connector tool the preset server does not advertise (no membership)', function (): void {
    [$server] = smtPresetServerWithTool();
    $foreignCatalogTool = ConnectorTool::factory()->create(['connector_slug' => 'leads', 'name' => 'other_tool']);

    expect(fn () => app(SetMcpToolPermissionAction::class)->execute($server, $foreignCatalogTool->id, McpToolPermission::Blocked))
        ->toThrow(McpToolNotFoundException::class);
})->skip(fn (): bool => ! Schema::hasTable('mcp_server_tool') || ! Schema::hasColumn('mcp_servers', 'uses_central_catalog'), 'schema out of date — run migrate:fresh.');
