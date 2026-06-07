<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Actions\SetMcpToolPermissionAction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;

/**
 * SetMcpToolPermissionAction writes to the tool-approval surface that gates
 * whether the LLM auto-executes a destructive write tool. The three-state
 * transition (Allow → Approval → Blocked, and any other combo) must persist
 * exactly the requested state, and the action must refuse to write across
 * server boundaries — a tool id that belongs to a different server raises
 * a RuntimeException so the controller can surface a 404.
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

it('throws RuntimeException for a tool id that belongs to a DIFFERENT server', function (): void {
    [$server] = smtServerWithTool();
    [, $otherTool] = smtServerWithTool();

    expect(fn () => app(SetMcpToolPermissionAction::class)->execute($server, $otherTool->id, McpToolPermission::Blocked))
        ->toThrow(RuntimeException::class);

    expect($otherTool->refresh()->permission)->not->toBe(McpToolPermission::Blocked);
});
