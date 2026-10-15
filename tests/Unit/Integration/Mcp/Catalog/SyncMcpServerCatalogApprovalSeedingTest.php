<?php

use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Mcp\Services\McpClient;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

/**
 * A custom server's own tools take their scope and first permission from the
 * server's `readOnlyHint`. The servers here are custom (no connector_slug), so
 * the sync skips the catalog description backfill and the tests see only the
 * tool seeding.
 */

/**
 * A read tool, a write tool and a tool with no hints, as `tools/list` lists them.
 *
 * @return list<array<string, mixed>>
 */
function apsTools(): array
{
    return [
        ['name' => 'read_tool', 'description' => 'reads', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_tool', 'description' => 'writes', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => false]],
        ['name' => 'unknown_tool', 'description' => 'no hints', 'inputSchema' => ['type' => 'object', 'properties' => []]],
    ];
}

it('seeds read tools to Allow and write tools to Approval, with the scope from readOnlyHint', function (): void {
    Queue::fake();

    $server = AgentMcpServer::factory()->create();

    $this->mock(McpClient::class, fn (MockInterface $mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom(apsTools())));

    app(SyncMcpServerCatalogAction::class)->execute($server);

    $tool = fn (string $name): McpTool => McpTool::query()
        ->where('mcp_server_id', $server->id)->where('name', $name)->sole();

    // A tool with no readOnlyHint may change data: it falls to the Write
    // scope and waits for approval like any write tool.
    expect($tool('read_tool')->permission)->toBe(McpToolPermission::Allow)
        ->and($tool('read_tool')->scope)->toBe(McpToolScope::Read)
        ->and($tool('write_tool')->permission)->toBe(McpToolPermission::Approval)
        ->and($tool('write_tool')->scope)->toBe(McpToolScope::Write)
        ->and($tool('unknown_tool')->permission)->toBe(McpToolPermission::Approval)
        ->and($tool('unknown_tool')->scope)->toBe(McpToolScope::Write);
});

it('does not clobber a user-set permission on a re-sync', function (): void {
    Queue::fake();

    $server = AgentMcpServer::factory()->create();

    $this->mock(McpClient::class, fn (MockInterface $mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom(apsTools())));

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

    $server = AgentMcpServer::factory()->create();
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
