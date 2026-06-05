<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Mcp\Services\McpCatalog;
use Domain\Integration\Mcp\Services\McpClient;
use Illuminate\Support\Facades\Queue;

function approvalServer(): AgentMcpServer
{
    $user = User::factory()->create();

    // connector_slug null → skip the catalog-preset description backfill so the
    // test focuses purely on the tool-seeding behaviour.
    return AgentMcpServer::create([
        'user_id' => $user->id,
        'connector_slug' => null,
        'name' => 'Seeding Server',
        'slug' => 'seeding',
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
}

function fakeTools(): array
{
    return [
        ['name' => 'read_tool', 'description' => 'reads', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_tool', 'description' => 'writes', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => false]],
        ['name' => 'unknown_tool', 'description' => 'no hints', 'inputSchema' => ['type' => 'object', 'properties' => []]],
    ];
}

it('seeds per-tool permission from annotations on first catalog', function (): void {
    Queue::fake();

    $server = approvalServer();

    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(fakeTools()));

    app(McpCatalog::class)->refresh($server);

    expect(McpTool::where('name', 'read_tool')->first()->permission)->toBe(McpToolPermission::Allow)
        ->and(McpTool::where('name', 'write_tool')->first()->permission)->toBe(McpToolPermission::Approval)
        ->and(McpTool::where('name', 'unknown_tool')->first()->permission)->toBe(McpToolPermission::Approval);
});

it('derives scope from the readOnlyHint annotation', function (): void {
    Queue::fake();

    $server = approvalServer();

    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(fakeTools()));

    app(McpCatalog::class)->refresh($server);

    expect(McpTool::where('name', 'read_tool')->first()->scope)->toBe(McpToolScope::Read)
        ->and(McpTool::where('name', 'write_tool')->first()->scope)->toBe(McpToolScope::Write)
        ->and(McpTool::where('name', 'unknown_tool')->first()->scope)->toBe(McpToolScope::Write);
});

it('does not clobber a user-set permission on a re-sync', function (): void {
    Queue::fake();

    $server = approvalServer();

    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(fakeTools()));

    app(McpCatalog::class)->refresh($server);

    // User flips the write tool to Allow.
    McpTool::where('mcp_server_id', $server->id)->where('name', 'write_tool')->update(['permission' => McpToolPermission::Allow->value]);

    // Re-sync must preserve the user's choice.
    app(McpCatalog::class)->refresh($server->fresh());

    expect(McpTool::where('mcp_server_id', $server->id)->where('name', 'write_tool')->first()->permission)
        ->toBe(McpToolPermission::Allow);
});

it('re-syncs scope when the server upgrades a tool from read to write', function (): void {
    Queue::fake();

    $server = approvalServer();
    $client = $this->mock(McpClient::class);

    $client->shouldReceive('fetchTools')->once()->andReturn([
        ['name' => 'thing', 'description' => 'reads', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => true]],
    ]);
    app(McpCatalog::class)->refresh($server);
    expect(McpTool::where('name', 'thing')->first()->scope)->toBe(McpToolScope::Read);

    $client->shouldReceive('fetchTools')->once()->andReturn([
        ['name' => 'thing', 'description' => 'now writes', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => false]],
    ]);
    app(McpCatalog::class)->refresh($server->fresh());

    expect(McpTool::where('name', 'thing')->first()->scope)->toBe(McpToolScope::Write);
});
