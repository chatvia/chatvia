<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Jobs\EmbedConnectorToolsJob;
use Domain\Conversation\Jobs\EmbedMcpToolsJob;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Mcp\Services\McpCatalog;
use Domain\Integration\Mcp\Services\McpClient;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * The preset/builtin path stores each connector's tool definitions + embeddings
 * ONCE in the shared `connector_tools` catalog (keyed by connector_slug), with a
 * thin per-workspace `mcp_server_tool` membership carrying that workspace's
 * permission. No matter how many workspaces connect "weather", the catalog holds
 * one row set.
 *
 * Only servers fetched from the connector's CANONICAL URL may share the catalog:
 * a preset whose URL the workspace supplies (requires_url / BYO-OAuth, e.g.
 * Zendesk) is tenant-specific and keeps per-server `mcp_tools` rows, so one
 * workspace can never write tool text another workspace's agents read.
 */
pest()->beforeEach(function (): void {
    if (! Schema::hasTable('connector_tools') || ! Schema::hasColumn('mcp_servers', 'uses_central_catalog')) {
        $this->markTestSkipped('schema out of date — run migrate:fresh on the testing DB.');
    }
});

function presetServer(string $connectorSlug, string $url, McpServerCategory $category = McpServerCategory::Integration): AgentMcpServer
{
    $user = User::factory()->create();

    return AgentMcpServer::create([
        'user_id' => $user->id,
        'connector_slug' => $connectorSlug,
        'category' => $category,
        'name' => ucfirst($connectorSlug),
        'slug' => $connectorSlug,
        'url' => $url,
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
}

function presetTools(): array
{
    return [
        ['name' => 'read_thing', 'description' => 'reads', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_thing', 'description' => 'writes', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => false]],
    ];
}

it('stores one shared catalog row per (connector_slug, name) regardless of how many workspaces connect', function (): void {
    Queue::fake();
    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(presetTools()));

    $internalUrl = rtrim((string) config('app.url'), '/').'/mcp/weather';
    $a = presetServer('weather', $internalUrl, McpServerCategory::Builtin);
    $b = presetServer('weather', $internalUrl, McpServerCategory::Builtin);

    app(McpCatalog::class)->refresh($a);
    app(McpCatalog::class)->refresh($b);

    // Two workspaces, but the catalog holds exactly one row per tool.
    expect(ConnectorTool::where('connector_slug', 'weather')->count())->toBe(2);

    // Each workspace gets its own membership rows.
    expect(McpServerTool::where('mcp_server_id', $a->id)->count())->toBe(2)
        ->and(McpServerTool::where('mcp_server_id', $b->id)->count())->toBe(2);
});

it('seeds membership permission from the catalog default — read tools Allow, writes gate', function (): void {
    Queue::fake();
    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(presetTools()));

    $server = presetServer('weather', rtrim((string) config('app.url'), '/').'/mcp/weather', McpServerCategory::Builtin);

    app(McpCatalog::class)->refresh($server);

    $permissionOf = function (string $name) use ($server): McpToolPermission {
        $toolId = ConnectorTool::where('connector_slug', 'weather')->where('name', $name)->value('id');

        return McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $toolId)->first()->permission;
    };

    expect($permissionOf('read_thing'))->toBe(McpToolPermission::Allow)
        ->and($permissionOf('write_thing'))->toBe(McpToolPermission::Approval)
        // The shared catalog default mirrors the seed.
        ->and(ConnectorTool::where('connector_slug', 'weather')->where('name', 'read_thing')->value('default_permission'))
        ->toBe(McpToolPermission::Allow);
});

it('does not clobber a workspace permission toggle on a re-sync', function (): void {
    Queue::fake();
    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(presetTools()));

    $server = presetServer('weather', rtrim((string) config('app.url'), '/').'/mcp/weather', McpServerCategory::Builtin);
    app(McpCatalog::class)->refresh($server);

    $writeId = ConnectorTool::where('connector_slug', 'weather')->where('name', 'write_thing')->value('id');

    // Workspace flips the write tool to Allow.
    McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $writeId)
        ->update(['permission' => McpToolPermission::Allow->value]);

    app(McpCatalog::class)->refresh($server->fresh());

    expect(McpServerTool::where('mcp_server_id', $server->id)->where('connector_tool_id', $writeId)->first()->permission)
        ->toBe(McpToolPermission::Allow);
});

it('reconciles membership when the server stops advertising a tool', function (): void {
    Queue::fake();
    $client = $this->mock(McpClient::class);

    $server = presetServer('weather', rtrim((string) config('app.url'), '/').'/mcp/weather', McpServerCategory::Builtin);

    $client->shouldReceive('fetchTools')->once()->andReturn(presetTools());
    app(McpCatalog::class)->refresh($server);
    expect(McpServerTool::where('mcp_server_id', $server->id)->count())->toBe(2);

    // Server now only advertises one tool — membership drops to match.
    $client->shouldReceive('fetchTools')->once()->andReturn([presetTools()[0]]);
    app(McpCatalog::class)->refresh($server->fresh());
    expect(McpServerTool::where('mcp_server_id', $server->id)->count())->toBe(1);
});

it('queues a connector-scoped embed job after a preset refresh', function (): void {
    Queue::fake();
    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(presetTools()));

    $server = presetServer('weather', rtrim((string) config('app.url'), '/').'/mcp/weather', McpServerCategory::Builtin);
    app(McpCatalog::class)->refresh($server);

    Queue::assertPushed(EmbedConnectorToolsJob::class, fn (EmbedConnectorToolsJob $job): bool => $job->connectorSlug === 'weather');
});

it('persists the storage shape on refresh — canonical URL → central catalog', function (): void {
    Queue::fake();
    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(presetTools()));

    $server = presetServer('weather', rtrim((string) config('app.url'), '/').'/mcp/weather', McpServerCategory::Builtin);
    expect($server->usesCentralCatalog())->toBeFalse();

    app(McpCatalog::class)->refresh($server);

    expect($server->fresh()->usesCentralCatalog())->toBeTrue();
});

it('keeps a preset with a workspace-supplied URL OUT of the shared catalog — per-server rows instead', function (): void {
    Queue::fake();
    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(presetTools()));

    // `zendesk` is a requires_url preset (mcp_url '' in config/connectors.php):
    // every workspace pastes its own instance URL, so definitions are
    // tenant-specific and must never be shared.
    $server = presetServer('zendesk', 'https://acme.example.com/api/mcp');

    // Simulate a stale central-shape leftover (e.g. the server flipped shape):
    // refresh must clear it, or its old shared definitions keep serving.
    $stale = ConnectorTool::factory()->create(['connector_slug' => 'zendesk', 'name' => 'old_tool']);
    McpServerTool::create([
        'mcp_server_id' => $server->id,
        'connector_tool_id' => $stale->id,
        'permission' => McpToolPermission::Allow,
    ]);

    app(McpCatalog::class)->refresh($server);

    expect($server->fresh()->uses_central_catalog)->toBeFalse()
        // Tools landed as per-server rows, not shared catalog rows.
        ->and(McpTool::where('mcp_server_id', $server->id)->count())->toBe(2)
        ->and(ConnectorTool::where('connector_slug', 'zendesk')->where('name', 'read_thing')->exists())->toBeFalse()
        // The stale membership is gone; embedding runs per-server.
        ->and(McpServerTool::where('mcp_server_id', $server->id)->count())->toBe(0);

    Queue::assertPushed(EmbedMcpToolsJob::class, fn (EmbedMcpToolsJob $job): bool => $job->mcpServerId === $server->id);
    Queue::assertNotPushed(EmbedConnectorToolsJob::class);
});

it('prunes catalog rows no server advertises anymore, after the staleness window', function (): void {
    Queue::fake();
    $this->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(presetTools()));

    $orphanStale = ConnectorTool::factory()->create(['connector_slug' => 'weather', 'name' => 'gone_tool', 'fetched_at' => now()->subDays(8)]);
    $orphanFresh = ConnectorTool::factory()->create(['connector_slug' => 'weather', 'name' => 'new_tool', 'fetched_at' => now()]);

    $server = presetServer('weather', rtrim((string) config('app.url'), '/').'/mcp/weather', McpServerCategory::Builtin);
    app(McpCatalog::class)->refresh($server);

    expect(ConnectorTool::whereKey($orphanStale->id)->exists())->toBeFalse()
        ->and(ConnectorTool::whereKey($orphanFresh->id)->exists())->toBeTrue();
});
