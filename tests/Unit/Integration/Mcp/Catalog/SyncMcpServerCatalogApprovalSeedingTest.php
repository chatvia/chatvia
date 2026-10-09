<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Mcp\Services\McpClient;
use Illuminate\Support\Facades\Queue;

function approvalServer(?string $url = null, string $slug = 'seeding'): AgentMcpServer
{
    $user = User::factory()->create();

    // connector_slug null → skip the catalog-preset description backfill so the
    // test focuses purely on the tool-seeding behaviour.
    return AgentMcpServer::create([
        'user_id' => $user->id,
        'connector_slug' => null,
        'name' => 'Seeding Server',
        'slug' => $slug,
        'url' => $url ?? 'https://mcp.example.test',
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

it('seeds read tools to Allow and write tools to Approval on a remote server', function (): void {
    Queue::fake();

    $server = approvalServer();

    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom(fakeTools())));

    app(SyncMcpServerCatalogAction::class)->execute($server);

    $permissionOf = fn (string $name): McpToolPermission => McpTool::query()
        ->where('mcp_server_id', $server->id)->where('name', $name)->first()->permission;

    // Read-only tools auto-Allow; writes (and tools with no readOnlyHint, which
    // fall to the Write scope) gate on Approval — the server's origin no longer
    // changes the seed.
    expect($permissionOf('read_tool'))->toBe(McpToolPermission::Allow)
        ->and($permissionOf('write_tool'))->toBe(McpToolPermission::Approval)
        ->and($permissionOf('unknown_tool'))->toBe(McpToolPermission::Approval);
});

it('seeds an internal-URL server the same way — read Allow, write Approval', function (): void {
    Queue::fake();

    // An internal URL (one of Chatvia's own built-in servers) gets the same
    // origin-independent seeding as any remote server.
    $server = approvalServer(
        url: rtrim((string) config('app.url'), '/').'/mcp/builtin-thing',
        slug: 'builtin',
    );

    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom(fakeTools())));

    app(SyncMcpServerCatalogAction::class)->execute($server);

    $permissionOf = fn (string $name): McpToolPermission => McpTool::query()
        ->where('mcp_server_id', $server->id)->where('name', $name)->first()->permission;

    expect($permissionOf('read_tool'))->toBe(McpToolPermission::Allow)
        ->and($permissionOf('write_tool'))->toBe(McpToolPermission::Approval)
        ->and($permissionOf('unknown_tool'))->toBe(McpToolPermission::Approval);
});

it('derives scope from the readOnlyHint annotation', function (): void {
    Queue::fake();

    $server = approvalServer();

    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom(fakeTools())));

    app(SyncMcpServerCatalogAction::class)->execute($server);

    expect(McpTool::where('name', 'read_tool')->first()->scope)->toBe(McpToolScope::Read)
        ->and(McpTool::where('name', 'write_tool')->first()->scope)->toBe(McpToolScope::Write)
        ->and(McpTool::where('name', 'unknown_tool')->first()->scope)->toBe(McpToolScope::Write);
});

it('does not clobber a user-set permission on a re-sync', function (): void {
    Queue::fake();

    $server = approvalServer();

    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom(fakeTools())));

    app(SyncMcpServerCatalogAction::class)->execute($server);

    // User flips the write tool to Allow.
    McpTool::where('mcp_server_id', $server->id)->where('name', 'write_tool')->update(['permission' => McpToolPermission::Allow->value]);

    // Re-sync must preserve the user's choice.
    app(SyncMcpServerCatalogAction::class)->execute($server->fresh());

    expect(McpTool::where('mcp_server_id', $server->id)->where('name', 'write_tool')->first()->permission)
        ->toBe(McpToolPermission::Allow);
});

it('re-syncs scope when the server upgrades a tool from read to write', function (): void {
    Queue::fake();

    $server = approvalServer();
    $client = $this->mock(McpClient::class);

    $client->shouldReceive('fetchTools')->once()->andReturn(McpToolDefinitionData::listFrom([
        ['name' => 'thing', 'description' => 'reads', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => true]],
    ]));
    app(SyncMcpServerCatalogAction::class)->execute($server);
    expect(McpTool::where('name', 'thing')->first()->scope)->toBe(McpToolScope::Read);

    $client->shouldReceive('fetchTools')->once()->andReturn(McpToolDefinitionData::listFrom([
        ['name' => 'thing', 'description' => 'now writes', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => false]],
    ]));
    app(SyncMcpServerCatalogAction::class)->execute($server->fresh());

    expect(McpTool::where('name', 'thing')->first()->scope)->toBe(McpToolScope::Write);
});
