<?php

use App\Identity\Workspace\Models\User;
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
        'requires_approval' => true,
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

it('seeds per-tool requires_approval from annotations on first catalog', function (): void {
    Queue::fake();

    $server = approvalServer();

    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(fakeTools()));

    app(McpCatalog::class)->refresh($server);

    expect(McpTool::where('name', 'read_tool')->first()->requires_approval)->toBeFalse()
        ->and(McpTool::where('name', 'write_tool')->first()->requires_approval)->toBeTrue()
        ->and(McpTool::where('name', 'unknown_tool')->first()->requires_approval)->toBeTrue();
});

it('does not clobber a user-toggled requires_approval on a re-sync', function (): void {
    Queue::fake();

    $server = approvalServer();

    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(fakeTools()));

    app(McpCatalog::class)->refresh($server);

    // User turns approval OFF for the write tool.
    McpTool::where('mcp_server_id', $server->id)->where('name', 'write_tool')->update(['requires_approval' => false]);

    // Re-sync must preserve the user's override.
    app(McpCatalog::class)->refresh($server->fresh());

    expect(McpTool::where('mcp_server_id', $server->id)->where('name', 'write_tool')->first()->requires_approval)->toBeFalse();
});
