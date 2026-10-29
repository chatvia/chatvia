<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Integration\Tool\Data\ToolEntryData;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/**
 * "Refresh tools" on a custom (per-server) connector must leave the catalog
 * exactly as the server advertises it NOW: tools it dropped disappear, tools it
 * added appear, tools whose full name an LLM would reject are left out
 * (`mcp__hejly-tutor__` is 18 characters of the 64 allowed), and every agent
 * using the connector sees the new pool on its next turn. Each tool takes its
 * scope from its `readOnlyHint` (none means it may change data) but starts by
 * asking a person (the server's address came from the workspace, so its
 * hints are not trusted); a permission the user set survives a re-sync until
 * the tool changes, and a block survives even that. A failed fetch keeps the
 * last good catalog and shows the error on the server.
 */

/** A `tools/list` entry; a null $readOnly leaves out the hint. */
function sotTool(string $name, string $description = 'does things', ?bool $readOnly = true): array
{
    return [
        'name' => $name,
        'description' => $description,
        'inputSchema' => ['type' => 'object', 'properties' => []],
        ...($readOnly === null ? [] : ['annotations' => ['readOnlyHint' => $readOnly]]),
    ];
}

/**
 * Makes the mocked server advertise these raw `tools/list` entries.
 *
 * @param  list<array<string, mixed>>  $tools
 */
function sotAdvertise(array $tools): void
{
    test()->mock(McpClient::class, fn (MockInterface $mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom($tools)));
}

beforeEach(function (): void {
    Queue::fake();
    $this->server = AgentMcpServer::factory()->create(['slug' => 'hejly-tutor']);
});

it('replaces the catalog with the tools the server advertises now, leaving out names an LLM would reject, and busts the agents\' pools', function (): void {
    $agent = createAgent($this->server->workspace->owner);
    $agent->attachConnector($this->server);
    $names = fn (): array => array_map(fn (ToolEntryData $e): string => $e->name, app(ToolRegistry::class)->for($agent));
    sotAdvertise([sotTool('get-learner-profile'), sotTool('ask-tutor')]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    $before = $names();

    sotAdvertise([
        sotTool('ask-tutor'),
        sotTool('correct-writing'),
        sotTool(Str::repeat('a', 46), 'fits exactly'),
        sotTool(Str::repeat('b', 47), 'one character too long'),
        sotTool('ask.tutor', 'has a dot'),
        sotTool('ask tutor', 'has a space'),
    ]);
    $tools = app(SyncMcpServerCatalogAction::class)->execute($this->server);

    expect($before)->toEqualCanonicalizing(['mcp__hejly-tutor__get-learner-profile', 'mcp__hejly-tutor__ask-tutor'])
        ->and($tools->pluck('name')->all())->toBe([Str::repeat('a', 46), 'ask-tutor', 'correct-writing'])
        ->and(McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', 'get-learner-profile')->exists())->toBeFalse()
        ->and($this->server->fresh()->catalog_tools_count)->toBe(3)
        ->and($names())->toEqualCanonicalizing(['mcp__hejly-tutor__'.Str::repeat('a', 46), 'mcp__hejly-tutor__ask-tutor', 'mcp__hejly-tutor__correct-writing']);
});

it('seeds every tool to Approval with the scope from its hint, and asks again only once an allowed tool changed', function (): void {
    $tool = fn (string $name): McpTool => McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', $name)->sole();
    $permit = fn (string $name, McpToolPermission $permission): int => McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', $name)->update(['permission' => $permission->value]);
    sotAdvertise([sotTool('read_tool', 'reads'), sotTool('write_tool', 'writes', false), sotTool('unknown_tool', 'no hints', null), sotTool('kept_tool', 'keeps')]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    $seeded = collect(['read_tool', 'write_tool', 'unknown_tool'])->mapWithKeys(fn (string $name): array => [$name => [$tool($name)->permission, $tool($name)->scope]])->all();
    $permit('read_tool', McpToolPermission::Allow);
    $permit('write_tool', McpToolPermission::Allow);
    $permit('kept_tool', McpToolPermission::Blocked);

    sotAdvertise([sotTool('read_tool', 'reads, now writes', false), sotTool('write_tool', 'writes', false), sotTool('unknown_tool', 'no hints', null), sotTool('kept_tool', 'keeps and deletes')]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server->fresh());

    expect($seeded)->toBe([
        'read_tool' => [McpToolPermission::Approval, McpToolScope::Read],
        'write_tool' => [McpToolPermission::Approval, McpToolScope::Write],
        'unknown_tool' => [McpToolPermission::Approval, McpToolScope::Write],
    ])
        ->and($tool('write_tool')->permission)->toBe(McpToolPermission::Allow)
        ->and($tool('read_tool')->permission)->toBe(McpToolPermission::Approval)
        ->and($tool('read_tool')->scope)->toBe(McpToolScope::Write)
        ->and($tool('kept_tool')->permission)->toBe(McpToolPermission::Blocked);
});

it('keeps the last good catalog and stores the error when the tools cannot be fetched', function (): void {
    sotAdvertise([sotTool('ask-tutor')]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);

    $this->mock(McpClient::class, fn (MockInterface $mock) => $mock->shouldReceive('fetchTools')
        ->andThrow(new McpException('MCP server returned HTTP 503: down', 503)));

    expect(fn (): Collection => app(SyncMcpServerCatalogAction::class)->execute($this->server->fresh()))
        ->toThrow(McpException::class, 'MCP server returned HTTP 503: down');

    expect($this->server->fresh()->last_error)->toBe('MCP server returned HTTP 503: down')
        ->and(McpTool::query()->where('mcp_server_id', $this->server->id)->pluck('name')->all())->toBe(['ask-tutor']);
});
