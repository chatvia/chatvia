<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Registry\ToolRegistry;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Domain\Integration\Mcp\Services\Routing\BuiltinToolRouter;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\DB;
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

        public function rerank(string $query, array $documents, ?int $topN = null): ?array
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

/**
 * Signature + identity headers for a POST to one of our internal MCP routes,
 * signed exactly as McpClient signs it (postJson sends json_encode($payload)).
 *
 * @param  array<string, mixed>  $payload
 * @param  array<string, string>  $identity
 * @return array<string, string>
 */
function signedInternalMcpHeaders(string $path, array $payload, array $identity): array
{
    return array_merge($identity, InternalMcpSignature::headers('POST', $path, (string) json_encode($payload), $identity));
}

/**
 * Provision + attach the (now opt-in) charts connector to an agent the way a
 * workspace would: an account-level row sharing the central catalog, a
 * membership for each synced charts tool, and the agent pivot. Assumes
 * `app:sync-builtin-mcp` has already populated the shared `connector_tools`
 * catalog for 'charts'.
 */
function attachCharts(Agent $agent): AgentMcpServer
{
    $server = AgentMcpServer::create([
        'user_id' => $agent->user_id,
        'category' => McpServerCategory::Builtin,
        'connector_slug' => 'charts',
        'name' => 'Interactive charts',
        'slug' => 'charts',
        'url' => rtrim((string) config('app.url'), '/').'/mcp/charts',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'uses_central_catalog' => true,
        'is_enabled' => true,
    ]);

    foreach (ConnectorTool::where('connector_slug', 'charts')->get() as $tool) {
        DB::table('mcp_server_tool')->insert([
            'mcp_server_id' => $server->id,
            'connector_tool_id' => $tool->id,
            'permission' => $tool->default_permission->value,
        ]);
    }

    $agent->mcpServers()->attach($server->id);
    app(ToolRegistry::class)->forget($agent->id);

    return $server;
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

it('does not make charts ambient — an unattached agent gets neither charts nor forms', function (): void {
    bindFakeEmbeddings();
    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();

    $agent = Agent::factory()->create(); // nothing attached

    $names = array_map(
        static fn ($entry): string => $entry->name,
        app(ToolRegistry::class)->for($agent),
    );

    // Charts is now opt-in (like forms): not attached → not in the pool.
    expect(collect($names)->contains(fn (string $n): bool => str_starts_with($n, 'mcp__charts__')))->toBeFalse();
    expect(collect($names)->contains(fn (string $n): bool => str_starts_with($n, 'mcp__forms__')))->toBeFalse();
})->skip(fn (): bool => ! Schema::hasColumn('mcp_servers', 'category'), 'mcp_servers.category not migrated yet — run migrate:fresh.');

it('surfaces charts tools once the agent attaches the connector', function (): void {
    bindFakeEmbeddings();
    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();

    $agent = Agent::factory()->create();
    attachCharts($agent);

    $names = array_map(
        static fn ($entry): string => $entry->name,
        app(ToolRegistry::class)->for($agent),
    );

    expect(collect($names)->contains(fn (string $n): bool => str_starts_with($n, 'mcp__charts__')))->toBeTrue();
})->skip(fn (): bool => ! Schema::hasColumn('mcp_servers', 'category'), 'mcp_servers.category not migrated yet — run migrate:fresh.');

it('403s an unattached agent on the charts route, allows it once attached', function (): void {
    bindFakeEmbeddings();
    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();
    $agent = Agent::factory()->create();

    $identity = ['X-Chatvia-Agent-Id' => $agent->external_id];
    $call = fn (string $tool): array => ['jsonrpc' => '2.0', 'id' => '1', 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) []]];

    // Charts is opt-in now: an agent that hasn't enabled it is gated like any other connector.
    $this->withHeaders(signedInternalMcpHeaders('/mcp/charts', $call('render-bar-chart'), $identity))
        ->postJson('/mcp/charts', $call('render-bar-chart'))->assertStatus(403);
    // Forms (also opt-in, not attached) → still gated.
    $this->withHeaders(signedInternalMcpHeaders('/mcp/forms', $call('find-forms'), $identity))
        ->postJson('/mcp/forms', $call('find-forms'))->assertStatus(403);

    // Once charts is attached, the per-agent enablement check passes (no longer 403).
    attachCharts($agent);
    $charts = $this->withHeaders(signedInternalMcpHeaders('/mcp/charts', $call('render-bar-chart'), $identity))
        ->postJson('/mcp/charts', $call('render-bar-chart'));
    expect($charts->getStatusCode())->not->toBeIn([401, 403]);
})->skip(fn (): bool => ! Schema::hasColumn('mcp_servers', 'category'), 'mcp_servers.category not migrated yet — run migrate:fresh.');

it('still authorizes the analytics chat (workspace mode) on the charts route', function (): void {
    bindFakeEmbeddings();
    $this->artisan('app:sync-builtin-mcp')->assertSuccessful();

    // The owner "Ask your data" chat reaches /mcp/charts via a signed workspace
    // id, NOT the removed global-builtin carve-out — this must keep working.
    $workspace = Agent::factory()->create()->user;
    $call = ['jsonrpc' => '2.0', 'id' => '1', 'method' => 'tools/call', 'params' => ['name' => 'render-bar-chart', 'arguments' => (object) []]];
    $headers = signedInternalMcpHeaders('/mcp/charts', $call, ['X-Chatvia-Workspace-Id' => $workspace->external_id]);

    $resp = $this->withHeaders($headers)->postJson('/mcp/charts', $call);
    expect($resp->getStatusCode())->not->toBeIn([401, 403]);
})->skip(fn (): bool => ! Schema::hasColumn('mcp_servers', 'category'), 'mcp_servers.category not migrated yet — run migrate:fresh.');
