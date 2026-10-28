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
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/**
 * "Refresh tools" on a custom (per-server) connector must leave the catalog
 * exactly as the server advertises it NOW: tools it dropped disappear, tools it
 * added appear, tools whose wording changed get re-indexed, and every agent
 * using the connector sees the new pool on its next turn. Each tool takes its
 * scope from its `readOnlyHint` but starts by asking a person (the server's
 * address came from the workspace, so its hints are not trusted); a
 * permission the user set survives a re-sync until the tool changes. A failed
 * fetch keeps the last good catalog and shows the error on the server.
 */
/**
 * Makes the mocked server advertise these tools on `tools/list`.
 *
 * @param  array<int, array{0: string, 1: string}>  $tools  name + description pairs
 */
function sotAdvertise(array $tools): void
{
    $raw = array_map(fn (array $t): array => [
        'name' => $t[0],
        'description' => $t[1],
        'inputSchema' => ['type' => 'object', 'properties' => []],
        'annotations' => ['readOnlyHint' => true],
    ], $tools);

    sotAdvertiseRaw($raw);
}

/**
 * Makes the mocked server advertise these raw `tools/list` entries.
 *
 * @param  list<array<string, mixed>>  $tools
 */
function sotAdvertiseRaw(array $tools): void
{
    test()->mock(McpClient::class, fn (MockInterface $mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom($tools)));
}

/**
 * A read tool, a write tool and a tool with no hints, as `tools/list` lists them.
 *
 * @return list<array<string, mixed>>
 */
function sotHintedTools(): array
{
    return [
        ['name' => 'read_tool', 'description' => 'reads', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => true]],
        ['name' => 'write_tool', 'description' => 'writes', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => false]],
        ['name' => 'unknown_tool', 'description' => 'no hints', 'inputSchema' => ['type' => 'object', 'properties' => []]],
    ];
}

beforeEach(function (): void {
    Queue::fake();
    $this->server = AgentMcpServer::factory()->create(['slug' => 'hejly-tutor']);
});

it('drops tools the server no longer advertises and adds the new ones', function (): void {
    sotAdvertise([['get-learner-profile', 'profile'], ['ask-tutor', 'ask'], ['correct-writing', 'correct']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);

    sotAdvertise([['ask-tutor', 'ask'], ['correct-writing', 'correct'], ['explain-grammar', 'grammar']]);
    $tools = app(SyncMcpServerCatalogAction::class)->execute($this->server);

    expect($tools->pluck('name')->all())->toBe(['ask-tutor', 'correct-writing', 'explain-grammar'])
        ->and(McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', 'get-learner-profile')->exists())->toBeFalse()
        ->and($this->server->fresh()->catalog_tools_count)->toBe(3);
});

it('re-indexes a tool whose description changed and leaves untouched ones indexed', function (): void {
    sotAdvertise([['ask-tutor', 'old wording'], ['correct-writing', 'correct']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    McpTool::query()->where('mcp_server_id', $this->server->id)->update(['search_text' => 'indexed']);
    Queue::fake();

    sotAdvertise([['ask-tutor', 'new wording'], ['correct-writing', 'correct']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);

    $bm25 = fn (string $name): ?string => McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', $name)->value('search_text');

    expect($bm25('ask-tutor'))->toBeNull()
        ->and($bm25('correct-writing'))->not->toBeNull();
    Queue::assertPushed(EmbedMcpToolsJob::class, fn (EmbedMcpToolsJob $job): bool => $job->mcpServerId === $this->server->id);
});

it('busts every attached agent\'s cached tool pool so the next turn sees the fresh tools', function (): void {
    $agent = createAgent($this->server->workspace->owner);
    $agent->attachConnector($this->server);

    sotAdvertise([['ask-tutor', 'ask']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    $names = fn (): array => array_map(fn (ToolEntryData $e): string => $e->name, app(ToolRegistry::class)->for($agent));
    expect($names())->toBe(['mcp__hejly-tutor__ask-tutor']);

    sotAdvertise([['ask-tutor', 'ask'], ['correct-writing', 'correct']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);

    expect($names())->toBe(['mcp__hejly-tutor__ask-tutor', 'mcp__hejly-tutor__correct-writing']);
});

it('leaves out tools whose full name an LLM would reject', function (): void {
    // `mcp__hejly-tutor__` (18 characters) plus the tool name must stay within
    // the 64 letters, digits, `_` and `-` LLM providers accept.
    sotAdvertise([
        ['ask-tutor', 'ask'],
        [Str::repeat('a', 46), 'fits exactly'],
        [Str::repeat('b', 47), 'one character too long'],
        ['ask.tutor', 'has a dot'],
        ['ask tutor', 'has a space'],
    ]);

    $tools = app(SyncMcpServerCatalogAction::class)->execute($this->server);

    expect($tools->pluck('name')->all())->toBe([Str::repeat('a', 46), 'ask-tutor'])
        ->and($this->server->fresh()->catalog_tools_count)->toBe(2);
});

it('keeps the last good catalog and stores the error when the tools cannot be fetched', function (): void {
    sotAdvertise([['ask-tutor', 'ask']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);

    $this->mock(McpClient::class, fn (MockInterface $mock) => $mock->shouldReceive('fetchTools')
        ->andThrow(new McpException('MCP server returned HTTP 503: down', 503)));

    expect(fn (): Collection => app(SyncMcpServerCatalogAction::class)->execute($this->server->fresh()))
        ->toThrow(McpException::class, 'MCP server returned HTTP 503: down');

    expect($this->server->fresh()->last_error)->toBe('MCP server returned HTTP 503: down')
        ->and(McpTool::query()->where('mcp_server_id', $this->server->id)->pluck('name')->all())->toBe(['ask-tutor']);
});

it('skips a disabled server without calling it', function (): void {
    $server = AgentMcpServer::factory()->disabled()->create();
    McpTool::factory()->for($server, 'mcpServer')->create(['name' => 'ask-tutor']);

    $tools = app(SyncMcpServerCatalogAction::class)->execute($server);

    expect($tools)->toBeEmpty()
        ->and(McpTool::query()->where('mcp_server_id', $server->id)->pluck('name')->all())->toBe(['ask-tutor']);
    Http::assertNothingSent();
});

it('seeds every tool to Approval, with the scope from readOnlyHint', function (): void {
    sotAdvertiseRaw(sotHintedTools());

    app(SyncMcpServerCatalogAction::class)->execute($this->server);

    $tool = fn (string $name): McpTool => McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', $name)->sole();

    // The hint is the server's word: a read tool runs freely only once the
    // workspace allows it. A tool with no readOnlyHint may change data: it
    // falls to the Write scope.
    expect($tool('read_tool')->permission)->toBe(McpToolPermission::Approval)
        ->and($tool('read_tool')->scope)->toBe(McpToolScope::Read)
        ->and($tool('write_tool')->permission)->toBe(McpToolPermission::Approval)
        ->and($tool('write_tool')->scope)->toBe(McpToolScope::Write)
        ->and($tool('unknown_tool')->permission)->toBe(McpToolPermission::Approval)
        ->and($tool('unknown_tool')->scope)->toBe(McpToolScope::Write);
});

it('asks again once an allowed tool changed, but keeps a block', function (): void {
    $tool = fn (string $description): array => ['name' => 'thing', 'description' => $description, 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => true]];
    $permission = fn (): McpToolPermission => McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', 'thing')->sole()->permission;

    sotAdvertiseRaw([$tool('searches')]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    McpTool::query()->where('mcp_server_id', $this->server->id)->update(['permission' => McpToolPermission::Allow->value]);

    sotAdvertiseRaw([$tool('searches and deletes')]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server->fresh());
    expect($permission())->toBe(McpToolPermission::Approval);

    McpTool::query()->where('mcp_server_id', $this->server->id)->update(['permission' => McpToolPermission::Blocked->value]);
    sotAdvertiseRaw([$tool('searches, deletes and more')]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server->fresh());
    expect($permission())->toBe(McpToolPermission::Blocked);
});

it('keeps a permission the user set on a re-sync', function (): void {
    sotAdvertiseRaw(sotHintedTools());
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', 'write_tool')->update(['permission' => McpToolPermission::Allow->value]);

    app(SyncMcpServerCatalogAction::class)->execute($this->server->fresh());

    expect(McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', 'write_tool')->sole()->permission)
        ->toBe(McpToolPermission::Allow);
});

it('re-syncs the scope when the server turns a read tool into a write tool', function (): void {
    $tool = fn (bool $readOnly): array => ['name' => 'thing', 'description' => $readOnly ? 'reads' : 'now writes', 'inputSchema' => ['type' => 'object', 'properties' => []], 'annotations' => ['readOnlyHint' => $readOnly]];
    $scope = fn (): McpToolScope => McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', 'thing')->sole()->scope;

    sotAdvertiseRaw([$tool(true)]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    expect($scope())->toBe(McpToolScope::Read);

    sotAdvertiseRaw([$tool(false)]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server->fresh());

    expect($scope())->toBe(McpToolScope::Write);
});
