<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob;
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

/**
 * The preset/builtin path stores each connector's tool definitions + embeddings
 * ONCE in the shared `connector_tools` catalog (keyed by connector_slug), with a
 * thin per-workspace `connector_tool_mcp_server` membership carrying that workspace's
 * permission. No matter how many workspaces connect "weather", the catalog holds
 * one row set. (How rows and memberships are written: UpsertConnectorToolsAction
 * and ReconcileToolMembershipsAction.)
 *
 * Only servers fetched from the connector's CANONICAL URL may share the catalog:
 * a preset whose URL the workspace supplies (requires_url / BYO-OAuth, e.g.
 * Zendesk) is tenant-specific and keeps per-server `mcp_tools` rows, so one
 * workspace can never write tool text another workspace's agents read; a
 * stale shared membership left from a shape flip is cleared, or its old
 * shared definitions keep serving. A server that moves to the shared catalog
 * drops its own rows, or its agents would see its tools twice. Catalog rows
 * no server advertises are pruned after the staleness window.
 */

/**
 * A read tool and a write tool, as `tools/list` lists them.
 *
 * @return list<array<string, mixed>>
 */
function scsTools(): array
{
    return [
        ['name' => 'read_thing', 'description' => 'reads', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_thing', 'description' => 'writes', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => false]],
    ];
}

beforeEach(function (): void {
    Queue::fake();
    $this->mock(McpClient::class, fn (MockInterface $mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom(scsTools())));
});

it('stores one shared catalog row set however many workspaces connect, moving a server off its own rows', function (): void {
    $a = AgentMcpServer::factory()->builtin('weather')->create();
    $b = AgentMcpServer::factory()->builtin('weather')->create(['uses_central_catalog' => false]);
    McpTool::factory()->for($b, 'mcpServer')->create(['name' => 'read_thing']);

    app(SyncMcpServerCatalogAction::class)->execute($a);
    app(SyncMcpServerCatalogAction::class)->execute($b);

    expect(ConnectorTool::where('connector_slug', 'weather')->count())->toBe(2)
        ->and(McpServerTool::where('mcp_server_id', $a->id)->count())->toBe(2)
        ->and(McpServerTool::where('mcp_server_id', $b->id)->count())->toBe(2)
        ->and($b->fresh()->usesCentralCatalog())->toBeTrue()
        ->and(McpTool::where('mcp_server_id', $b->id)->exists())->toBeFalse();
    Queue::assertPushed(EmbedConnectorToolsJob::class, fn (EmbedConnectorToolsJob $job): bool => $job->connectorSlug === 'weather');
});

it('keeps a preset with a workspace-supplied URL OUT of the shared catalog — per-server rows instead', function (): void {
    $server = AgentMcpServer::factory()->preset('zendesk')->create(['url' => 'https://acme.example.com/api/mcp']);
    $stale = ConnectorTool::factory()->create(['connector_slug' => 'zendesk', 'name' => 'old_tool']);
    McpServerTool::create([
        'mcp_server_id' => $server->id,
        'connector_tool_id' => $stale->id,
        'permission' => McpToolPermission::Allow,
    ]);

    app(SyncMcpServerCatalogAction::class)->execute($server);

    expect($server->fresh()->uses_central_catalog)->toBeFalse()
        ->and(McpTool::where('mcp_server_id', $server->id)->count())->toBe(2)
        ->and(ConnectorTool::where('connector_slug', 'zendesk')->where('name', 'read_thing')->exists())->toBeFalse()
        ->and(McpServerTool::where('mcp_server_id', $server->id)->count())->toBe(0);
    Queue::assertPushed(EmbedMcpToolsJob::class, fn (EmbedMcpToolsJob $job): bool => $job->mcpServerId === $server->id);
    Queue::assertNotPushed(EmbedConnectorToolsJob::class);
});

it('prunes catalog rows no server advertises anymore, after the staleness window', function (): void {
    $orphanStale = ConnectorTool::factory()->create(['connector_slug' => 'weather', 'name' => 'gone_tool', 'fetched_at' => now()->subDays(8)]);
    $orphanFresh = ConnectorTool::factory()->create(['connector_slug' => 'weather', 'name' => 'new_tool', 'fetched_at' => now()]);
    $server = AgentMcpServer::factory()->builtin('weather')->create();

    app(SyncMcpServerCatalogAction::class)->execute($server);

    expect(ConnectorTool::whereKey($orphanStale->id)->exists())->toBeFalse()
        ->and(ConnectorTool::whereKey($orphanFresh->id)->exists())->toBeTrue();
});
