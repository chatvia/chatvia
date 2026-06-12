<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Registry\ToolRegistry;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Domain\Integration\Mcp\Services\Routing\BuiltinToolRouter;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Fake embeddings so `app:sync-builtin-mcp`'s inline indexing + the router's
 * query embedding don't reach the real provider. BM25 (a DB-side tokenizer)
 * still does the meaningful ranking.
 */
function bindFakeEmbeddings(): void
{
    app()->instance(EmbeddingsProviderInterface::class, new class implements EmbeddingsProviderInterface
    {
        public function embed(string $text, bool $returnAsText = false): string|array
        {
            return array_fill(0, 1024, 0.1);
        }

        public function embedQuery(string $text, bool $returnAsText = false): string|array
        {
            return array_fill(0, 1024, 0.1);
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn (string $text): array => (array) $this->embed($text), $texts);
        }

        public function rerank(string $query, array $documents): ?array
        {
            return null;
        }

        public function supportsReranking(): bool
        {
            return false;
        }

        public function getDimensions(): int
        {
            return 1024;
        }

        public function getModelName(): string
        {
            return 'fake-embed';
        }
    });
}

it('catalogs the built-in servers as global rows and indexes their tools', function (): void {
    bindFakeEmbeddings();

    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();

    $globals = AgentMcpServer::query()->whereNull('user_id')->get();
    expect($globals)->toHaveCount(2);

    $analytics = $globals->firstWhere('slug', 'account-analytics');
    expect($analytics->category)->toBe(McpServerCategory::Analytics)
        ->and($analytics->user_id)->toBeNull()
        ->and($analytics->connectorTools()->count())->toBe(16);

    $charts = $globals->firstWhere('slug', 'charts');
    expect($charts->category)->toBe(McpServerCategory::Builtin)
        ->and($charts->connectorTools()->count())->toBe(12);

    // Every tool got indexed (dense + BM25) into the shared catalog by the
    // inline embed step.
    expect(ConnectorTool::where('connector_slug', 'account-analytics')->whereNull('embedding')->count())->toBe(0)
        ->and(ConnectorTool::where('connector_slug', 'account-analytics')->whereNull('embedding_bm25')->count())->toBe(0);
})->skip(fn (): bool => ! Schema::hasColumn('mcp_servers', 'category'), 'mcp_servers.category not migrated yet — run migrate:fresh.');

it('is idempotent — re-running keeps two global rows', function (): void {
    bindFakeEmbeddings();

    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();
    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();

    expect(AgentMcpServer::query()->whereNull('user_id')->count())->toBe(2);
})->skip(fn (): bool => ! Schema::hasColumn('mcp_servers', 'category'), 'mcp_servers.category not migrated yet — run migrate:fresh.');

it('routes a question to the relevant global tools + a name→url map', function (): void {
    bindFakeEmbeddings();
    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();

    $result = app(BuiltinToolRouter::class)->route(
        'how many messages did I get this cycle',
        [McpServerCategory::Analytics, McpServerCategory::Builtin],
        6,
    );

    expect($result['definitions'])->not->toBeEmpty();

    $names = array_map(static fn (array $d): string => $d['function']['name'], $result['definitions']);

    // Every returned tool has a dispatch URL pointing at an internal /mcp/ route.
    foreach ($names as $name) {
        expect($result['routes'])->toHaveKey($name);
        expect($result['routes'][$name])->toContain('/mcp/');
    }
})->skip(fn (): bool => ! Schema::hasColumn('mcp_servers', 'category'), 'mcp_servers.category not migrated yet — run migrate:fresh.');

it('makes the global builtin (charts) ambient for every agent, but keeps leads per-agent', function (): void {
    bindFakeEmbeddings();
    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();

    $agent = Agent::factory()->create(); // nothing attached

    $names = array_map(
        static fn ($entry): string => $entry->name,
        app(ToolRegistry::class)->for($agent),
    );

    // Charts is global builtin (synced) → always available.
    expect(collect($names)->contains(fn (string $n): bool => str_starts_with($n, 'mcp__charts__')))->toBeTrue();
    // Leads is builtin but NOT synced as a global row → stays per-agent (not attached here).
    expect(collect($names)->contains(fn (string $n): bool => str_starts_with($n, 'mcp__leads__')))->toBeFalse();
})->skip(fn (): bool => ! Schema::hasColumn('mcp_servers', 'category'), 'mcp_servers.category not migrated yet — run migrate:fresh.');

it('lets an unattached agent call the always-on charts route, but 403s leads', function (): void {
    bindFakeEmbeddings();
    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();
    $agent = Agent::factory()->create();

    $sign = fn (): array => array_merge(InternalMcpSignature::headers(), ['X-Chatvia-Agent-Id' => $agent->external_id]);
    $call = fn (string $tool): array => ['jsonrpc' => '2.0', 'id' => '1', 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) []]];

    // Charts: global builtin → middleware allows (would be 403 without the rule).
    $charts = $this->withHeaders($sign())->postJson('/mcp/charts', $call('render-bar-chart'));
    expect($charts->getStatusCode())->not->toBe(403);

    // Leads: not global, not attached → still gated.
    $this->withHeaders($sign())->postJson('/mcp/leads', $call('capture-lead'))->assertStatus(403);
})->skip(fn (): bool => ! Schema::hasColumn('mcp_servers', 'category'), 'mcp_servers.category not migrated yet — run migrate:fresh.');
