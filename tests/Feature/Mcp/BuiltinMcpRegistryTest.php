<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Services\Routing\BuiltinToolRouter;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Support\Facades\DB;
use Tests\Support\Mcp\InternalMcp;

/**
 * Provision + attach the opt-in charts connector to an agent the way a
 * workspace would: an account-level row sharing the central catalog, a
 * membership for each synced charts tool, and the agent pivot. Assumes
 * `chatvia:mcp:sync-builtin` has already populated the shared `connector_tools`
 * catalog for 'charts'.
 */
function attachCharts(Agent $agent): AgentMcpServer
{
    $server = AgentMcpServer::create([
        'workspace_id' => $agent->workspace_id,
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
        DB::table('connector_tool_mcp_server')->insert([
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
    $this->artisan('chatvia:mcp:sync-builtin')->assertSuccessful();

    $globals = AgentMcpServer::query()->whereNull('workspace_id')->get();
    expect($globals)->toHaveCount(2);

    $analytics = $globals->firstWhere('slug', 'account-analytics');
    expect($analytics->category)->toBe(McpServerCategory::Analytics)
        ->and($analytics->workspace_id)->toBeNull()
        ->and($analytics->connectorTools()->count())->toBe(15);

    $charts = $globals->firstWhere('slug', 'charts');
    expect($charts->category)->toBe(McpServerCategory::Builtin)
        ->and($charts->connectorTools()->count())->toBe(12);

    // Every tool got indexed (dense + BM25) into the shared catalog by the
    // inline embed step.
    expect(ConnectorTool::where('connector_slug', 'account-analytics')->whereNull('embedding')->count())->toBe(0)
        ->and(ConnectorTool::where('connector_slug', 'account-analytics')->whereNull('search_text')->count())->toBe(0);
});

it('is idempotent — re-running keeps two global rows', function (): void {
    $this->artisan('chatvia:mcp:sync-builtin')->assertSuccessful();
    $this->artisan('chatvia:mcp:sync-builtin')->assertSuccessful();

    expect(AgentMcpServer::query()->whereNull('workspace_id')->count())->toBe(2);
});

it('routes a message-count question to the message stats tool, with its dispatch URL', function (): void {
    $this->artisan('chatvia:mcp:sync-builtin')->assertSuccessful();

    $result = app(BuiltinToolRouter::class)->route(
        'how many messages did I get this cycle',
        [McpServerCategory::Analytics, McpServerCategory::Builtin],
        6,
    );

    $names = array_map(static fn (array $d): string => $d['function']['name'], $result['definitions']);

    expect(collect($names)->contains(fn (string $name): bool => str_ends_with($name, 'get-message-stats-tool')))->toBeTrue();

    // Every returned tool has a dispatch URL pointing at an internal /mcp/ route.
    foreach ($names as $name) {
        expect($result['routes'])->toHaveKey($name);
        expect($result['routes'][$name])->toContain('/mcp/');
    }
});

it('does not make charts ambient — an unattached agent gets neither charts nor forms', function (): void {
    $this->artisan('chatvia:mcp:sync-builtin')->assertSuccessful();

    $agent = Agent::factory()->create(); // nothing attached

    $names = array_map(
        static fn (ToolEntry $entry): string => $entry->name,
        app(ToolRegistry::class)->for($agent),
    );

    // Charts is opt-in (like forms): not attached → not in the pool.
    expect(collect($names)->contains(fn (string $n): bool => str_starts_with($n, 'mcp__charts__')))->toBeFalse();
    expect(collect($names)->contains(fn (string $n): bool => str_starts_with($n, 'mcp__forms__')))->toBeFalse();
});

it('surfaces charts tools once the agent attaches the connector', function (): void {
    $this->artisan('chatvia:mcp:sync-builtin')->assertSuccessful();

    $agent = Agent::factory()->create();
    attachCharts($agent);

    $names = array_map(
        static fn (ToolEntry $entry): string => $entry->name,
        app(ToolRegistry::class)->for($agent),
    );

    expect(collect($names)->contains(fn (string $n): bool => str_starts_with($n, 'mcp__charts__')))->toBeTrue();
});

/** A bar chart call the tool accepts. */
function registryBarChartCall(): array
{
    return InternalMcp::toolCall('render-bar-chart-tool', [
        'categories' => ['Mon', 'Tue'],
        'series' => [['name' => 'Visitors', 'data' => [10, 20]]],
        'summary' => 'Visitors per day.',
    ]);
}

it('403s an unattached agent on the charts route, allows it once attached', function (): void {
    $this->artisan('chatvia:mcp:sync-builtin')->assertSuccessful();
    $agent = Agent::factory()->create();

    $identity = ['X-Chatvia-Agent-Id' => $agent->external_id];
    $chart = registryBarChartCall();
    $findForms = InternalMcp::toolCall('find-forms', ['intent' => 'book a demo']);

    // Charts is opt-in: an agent that hasn't enabled it is gated like any other connector.
    $this->withHeaders(InternalMcp::signedHeaders('/mcp/charts', $chart, $identity))
        ->postJson('/mcp/charts', $chart)->assertStatus(403);
    // Forms (also opt-in, not attached) → still gated.
    $this->withHeaders(InternalMcp::signedHeaders('/mcp/forms', $findForms, $identity))
        ->postJson('/mcp/forms', $findForms)->assertStatus(403);

    // Once charts is attached, the per-agent enablement check passes and the tool runs.
    attachCharts($agent);
    $this->withHeaders(InternalMcp::signedHeaders('/mcp/charts', $chart, $identity))
        ->postJson('/mcp/charts', $chart)
        ->assertOk()
        ->assertJsonPath('result.isError', false);
});

it('authorizes the analytics chat (workspace mode) on the charts route', function (): void {
    $this->artisan('chatvia:mcp:sync-builtin')->assertSuccessful();

    // The owner "Ask your data" chat reaches /mcp/charts with a signed
    // workspace id and no agent.
    $workspace = Agent::factory()->create()->workspace;
    $chart = registryBarChartCall();
    $headers = InternalMcp::signedHeaders('/mcp/charts', $chart, ['X-Chatvia-Workspace-Id' => $workspace->external_id]);

    $this->withHeaders($headers)->postJson('/mcp/charts', $chart)
        ->assertOk()
        ->assertJsonPath('result.isError', false);
});
