<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Illuminate\Support\Facades\Queue;

/**
 * "Refresh tools" on a custom (per-server) connector must leave the catalog
 * exactly as the server advertises it NOW: tools it dropped disappear, tools it
 * added appear, tools whose wording changed get re-indexed, and every agent
 * using the connector sees the new pool on its next turn.
 */
function customServer(): AgentMcpServer
{
    return AgentMcpServer::create([
        'workspace_id' => User::factory()->create()->workspace()->id,
        'connector_slug' => null,
        'name' => 'Hejly AI Tutor',
        'description' => 'Language tutor',
        'slug' => 'hejly-tutor',
        'url' => 'https://hejly.test/mcp/tutor',
        'transport' => 'streamable_http',
        'auth_type' => 'bearer',
        'is_enabled' => true,
    ]);
}

/** @param array<int, array{0: string, 1: string}> $tools name + description pairs */
function advertise(array $tools): void
{
    $raw = array_map(fn (array $t): array => [
        'name' => $t[0],
        'description' => $t[1],
        'inputSchema' => ['type' => 'object', 'properties' => []],
        'annotations' => ['readOnlyHint' => true],
    ], $tools);

    test()->mock(McpClient::class, fn ($mock) => $mock->shouldReceive('fetchTools')->andReturn(McpToolDefinitionData::listFrom($raw)));
}

beforeEach(function (): void {
    Queue::fake();
    $this->server = customServer();
});

it('drops tools the server no longer advertises and adds the new ones', function (): void {
    advertise([['get-learner-profile', 'profile'], ['ask-tutor', 'ask'], ['correct-writing', 'correct']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);

    advertise([['ask-tutor', 'ask'], ['correct-writing', 'correct'], ['explain-grammar', 'grammar']]);
    $tools = app(SyncMcpServerCatalogAction::class)->execute($this->server);

    expect($tools->pluck('name')->all())->toBe(['ask-tutor', 'correct-writing', 'explain-grammar'])
        ->and(McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', 'get-learner-profile')->exists())->toBeFalse()
        ->and($this->server->fresh()->catalog_tools_count)->toBe(3);
});

it('re-indexes a tool whose description changed and leaves untouched ones indexed', function (): void {
    advertise([['ask-tutor', 'old wording'], ['correct-writing', 'correct']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    McpTool::query()->where('mcp_server_id', $this->server->id)->update(['embedding_bm25' => Bm25Tokenizer::expression('indexed')]);
    Queue::fake();

    advertise([['ask-tutor', 'new wording'], ['correct-writing', 'correct']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);

    $bm25 = fn (string $name): ?string => McpTool::query()->where('mcp_server_id', $this->server->id)->where('name', $name)->value('embedding_bm25');

    expect($bm25('ask-tutor'))->toBeNull()
        ->and($bm25('correct-writing'))->not->toBeNull();
    Queue::assertPushed(EmbedMcpToolsJob::class, fn (EmbedMcpToolsJob $job): bool => $job->mcpServerId === $this->server->id);
});

it('busts every attached agent\'s cached tool pool so the next turn sees the fresh tools', function (): void {
    $agent = createAgent($this->server->workspace->owner);
    $agent->attachConnector($this->server);

    advertise([['ask-tutor', 'ask']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    $names = fn (): array => array_map(fn (ToolEntry $e): string => $e->name, app(ToolRegistry::class)->for($agent));
    expect($names())->toBe(['mcp__hejly-tutor__ask-tutor']);

    advertise([['ask-tutor', 'ask'], ['correct-writing', 'correct']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);

    expect($names())->toBe(['mcp__hejly-tutor__ask-tutor', 'mcp__hejly-tutor__correct-writing']);
});

it('re-indexes every tool when the server is renamed or re-described', function (): void {
    advertise([['ask-tutor', 'ask']]);
    app(SyncMcpServerCatalogAction::class)->execute($this->server);
    McpTool::query()->where('mcp_server_id', $this->server->id)->update(['embedding_bm25' => Bm25Tokenizer::expression('indexed')]);
    Queue::fake();

    $this->server->fresh()->update(['description' => 'Danish tutor for Hejly learners']);

    expect(McpTool::query()->where('mcp_server_id', $this->server->id)->value('embedding_bm25'))->toBeNull();
    Queue::assertPushed(EmbedMcpToolsJob::class);

    // An unrelated change (e.g. the catalog timestamp) leaves the index alone.
    McpTool::query()->where('mcp_server_id', $this->server->id)->update(['embedding_bm25' => Bm25Tokenizer::expression('indexed')]);
    Queue::fake();
    $this->server->fresh()->update(['last_error' => null, 'catalog_cached_at' => now()]);

    expect(McpTool::query()->where('mcp_server_id', $this->server->id)->value('embedding_bm25'))->not->toBeNull();
    Queue::assertNothingPushed();
});
