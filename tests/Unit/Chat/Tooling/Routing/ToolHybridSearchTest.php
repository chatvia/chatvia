<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Routing\ToolHybridSearch;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pgvector\Laravel\Vector;

/**
 * Runs the real six-channel RRF query against Postgres — the SQL (CTE shape,
 * bind order, LATERAL slug pick) is the unit under test, so nothing is mocked.
 * Preset reachability comes from the precomputed reachable-set CTEs: a catalog
 * tool surfaces iff one of the agent's servers (or a global builtin) advertises
 * it with an unblocked membership.
 */
pest()->beforeEach(function (): void {
    if (! Schema::hasTable('connector_tools') || ! Schema::hasColumn('mcp_servers', 'uses_central_catalog')) {
        $this->markTestSkipped('schema out of date — run migrate:fresh on the testing DB.');
    }
});

function hybridSearch(Agent $agent): array
{
    $zeroVector = array_fill(0, 1024, 0.0);

    return app(ToolHybridSearch::class)->search($agent, 'capture a new lead', $zeroVector);
}

function catalogToolWithMembership(AgentMcpServer $server, string $name, McpToolPermission $permission = McpToolPermission::Allow): ConnectorTool
{
    $tool = ConnectorTool::factory()->create([
        'connector_slug' => $server->connector_slug,
        'name' => $name,
        'embedding' => new Vector(array_fill(0, 1024, 0.0)),
    ]);

    McpServerTool::create([
        'mcp_server_id' => $server->id,
        'connector_tool_id' => $tool->id,
        'permission' => $permission,
    ]);

    return $tool;
}

it('returns an empty result set for an agent with no tools at all', function (): void {
    $agent = Agent::factory()->create();

    expect(hybridSearch($agent))->toBe([]);
});

it('surfaces a preset catalog tool through the agent-attached server, compiled with that server slug', function (): void {
    $agent = Agent::factory()->create();

    $server = AgentMcpServer::create([
        'user_id' => $agent->user_id,
        'connector_slug' => 'leads',
        'uses_central_catalog' => true,
        'name' => 'Leads',
        'slug' => 'leads',
        'url' => 'https://example.test/mcp/leads',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
    catalogToolWithMembership($server, 'capture_lead');

    DB::table('agent_mcp_server')->insert(['agent_id' => $agent->id, 'mcp_server_id' => $server->id]);

    $results = hybridSearch($agent);

    expect($results)->toHaveCount(1)
        ->and($results[0]['entry']->name)->toBe('mcp__leads__capture_lead')
        ->and($results[0]['kind'])->toBe('mcp_preset');

    // Another agent without the server attached sees nothing.
    expect(hybridSearch(Agent::factory()->create()))->toBe([]);
});

it('hides a catalog tool whose membership this workspace blocked', function (): void {
    $agent = Agent::factory()->create();

    $server = AgentMcpServer::create([
        'user_id' => $agent->user_id,
        'connector_slug' => 'leads',
        'uses_central_catalog' => true,
        'name' => 'Leads',
        'slug' => 'leads',
        'url' => 'https://example.test/mcp/leads',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
    catalogToolWithMembership($server, 'capture_lead', McpToolPermission::Blocked);

    DB::table('agent_mcp_server')->insert(['agent_id' => $agent->id, 'mcp_server_id' => $server->id]);

    expect(hybridSearch($agent))->toBe([]);
});
