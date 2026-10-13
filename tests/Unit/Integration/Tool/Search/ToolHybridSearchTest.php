<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Data\ToolSearchHitData;
use Domain\Integration\Tool\Enums\ToolSearchPool;
use Domain\Integration\Tool\Search\ToolHybridSearch;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Pgvector\Laravel\Vector;

/**
 * Runs the real six-channel RRF query against Postgres — the SQL (CTE shape,
 * bind order, LATERAL slug pick) is the unit under test, so nothing is mocked.
 * Preset reachability comes from the precomputed reachable-set CTEs: a catalog
 * tool surfaces iff one of the agent's attached servers advertises it with an
 * unblocked membership.
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
        'workspace_id' => $agent->workspace_id,
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
        ->and($results[0]->entry->name)->toBe('mcp__leads__capture_lead')
        ->and($results[0]->pool)->toBe(ToolSearchPool::McpPreset);

    // Another agent without the server attached sees nothing.
    expect(hybridSearch(Agent::factory()->create()))->toBe([]);
});

it('hides a catalog tool whose membership this workspace blocked', function (): void {
    $agent = Agent::factory()->create();

    $server = AgentMcpServer::create([
        'workspace_id' => $agent->workspace_id,
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

it('excludes the tools of an always-on connector from recall — they ship on every turn anyway', function (): void {
    $agent = Agent::factory()->create();

    $make = function (string $slug, bool $alwaysAvailable) use ($agent): void {
        $server = AgentMcpServer::create([
            'workspace_id' => $agent->workspace_id,
            'connector_slug' => $slug,
            'uses_central_catalog' => true,
            'name' => ucfirst($slug),
            'slug' => $slug,
            'url' => "https://example.test/mcp/{$slug}",
            'transport' => 'streamable_http',
            'auth_type' => 'none',
            'is_enabled' => true,
        ]);
        catalogToolWithMembership($server, 'capture_lead');
        DB::table('agent_mcp_server')->insert(['agent_id' => $agent->id, 'mcp_server_id' => $server->id, 'always_available' => $alwaysAvailable]);
    };
    $make('leads', alwaysAvailable: false);
    $make('tutor', alwaysAvailable: true);

    $names = array_map(fn (ToolSearchHitData $hit): string => $hit->entry->name, hybridSearch($agent));

    expect($names)->toBe(['mcp__leads__capture_lead']);
});

/** A unit vector at similarity `$similarity` to the query (axis 0), leaning on axis `$axis`. */
function toolSearchVector(float $similarity, int $axis): array
{
    $vector = array_fill(0, 1024, 0.0);
    $vector[0] = $similarity;
    $vector[$axis] = sqrt(1 - $similarity ** 2);

    return $vector;
}

function toolSearchCustomServer(Agent $agent, string $slug, bool $enabled = true): AgentMcpServer
{
    $server = AgentMcpServer::create([
        'workspace_id' => $agent->workspace_id,
        'uses_central_catalog' => false,
        'name' => ucfirst($slug),
        'slug' => $slug,
        'url' => "https://example.test/mcp/{$slug}",
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => $enabled,
    ]);
    DB::table('agent_mcp_server')->insert(['agent_id' => $agent->id, 'mcp_server_id' => $server->id]);

    return $server;
}

function toolSearchCustomTool(AgentMcpServer $server, string $name, array $vector, McpToolPermission $permission = McpToolPermission::Allow): void
{
    McpTool::query()->create([
        'mcp_server_id' => $server->id,
        'name' => $name,
        'description' => "Tool {$name}",
        'input_schema' => ['type' => 'object', 'properties' => []],
        'scope' => McpToolScope::Read,
        'permission' => $permission,
        'fetched_at' => now(),
    ])->forceFill([
        'embedding' => new Vector($vector),
        'embedding_bm25' => Bm25Tokenizer::expression("Tool {$name}"),
    ])->save();
}

function toolSearchFunction(int $workspaceId, string $name, array $vector, string $description, bool $enabled = true): AgentFunction
{
    // Without events: the observer would queue a real embedding. The id and
    // secret are what its creating hooks would fill in.
    $function = AgentFunction::withoutEvents(fn (): AgentFunction => AgentFunction::query()->create([
        'external_id' => (string) Str::uuid7(),
        'webhook_secret' => Str::random(48),
        'workspace_id' => $workspaceId,
        'internal_name' => $name,
        'description' => $description,
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => "https://example.test/hooks/{$name}",
        'webhook_method' => 'GET',
        'is_enabled' => $enabled,
    ]));
    $function->forceFill([
        'embedding' => new Vector($vector),
        'embedding_bm25' => Bm25Tokenizer::expression($name.' '.$description),
    ])->saveQuietly();

    return $function;
}

/** @return list<string> */
function toolSearchNames(Agent $agent, string $message): array
{
    $hits = app(ToolHybridSearch::class)->search($agent, $message, toolSearchVector(1.0, 1));

    return array_map(fn (ToolSearchHitData $hit): string => $hit->entry->name, $hits);
}

it('ranks the custom MCP tools of the agent\'s own servers by exact distance', function (): void {
    $agent = Agent::factory()->create();
    $server = toolSearchCustomServer($agent, 'crm');
    toolSearchCustomTool($server, 'find_contact', toolSearchVector(0.9, 2));
    toolSearchCustomTool($server, 'list_deals', toolSearchVector(0.6, 3));
    toolSearchCustomTool($server, 'delete_everything', toolSearchVector(1.0, 1), McpToolPermission::Blocked);
    toolSearchCustomTool(toolSearchCustomServer($agent, 'off', enabled: false), 'disabled_tool', toolSearchVector(1.0, 1));

    // Another agent's server holds the closest tool of all; it never surfaces.
    $other = Agent::factory()->create();
    toolSearchCustomTool(toolSearchCustomServer($other, 'theirs'), 'their_tool', toolSearchVector(1.0, 1));

    expect(toolSearchNames($agent, 'zzz'))->toBe(['mcp__crm__find_contact', 'mcp__crm__list_deals']);
});

it('ranks the agent\'s enabled functions by exact distance, never another workspace\'s', function (): void {
    $agent = Agent::factory()->create();
    $near = toolSearchFunction($agent->workspace_id, 'near_fn', toolSearchVector(0.9, 2), 'First helper.');
    $far = toolSearchFunction($agent->workspace_id, 'far_fn', toolSearchVector(0.5, 3), 'Second helper.');
    $off = toolSearchFunction($agent->workspace_id, 'off_fn', toolSearchVector(1.0, 1), 'Disabled helper.', enabled: false);
    $agent->functions()->attach([$near->id, $far->id, $off->id]);

    // Not attached to this agent, and another workspace's function: both
    // closer to the query than anything the agent has.
    toolSearchFunction($agent->workspace_id, 'unattached_fn', toolSearchVector(1.0, 1), 'Unattached helper.');
    $other = Agent::factory()->create();
    $other->functions()->attach(toolSearchFunction($other->workspace_id, 'their_fn', toolSearchVector(1.0, 1), 'Their helper.')->id);

    expect(toolSearchNames($agent, 'zzz'))->toBe(['near_fn', 'far_fn']);
});

it('counts a keyword hit only for a function that shares a term with the message', function (): void {
    $agent = Agent::factory()->create();
    $refund = toolSearchFunction($agent->workspace_id, 'issue_refund', toolSearchVector(0.5, 2), 'Pay a refund back to the customer.');
    $weather = toolSearchFunction($agent->workspace_id, 'mountain_weather', toolSearchVector(0.5, 3), 'Forecast for hiking trails.');
    $agent->functions()->attach([$refund->id, $weather->id]);

    $channels = collect(app(ToolHybridSearch::class)->search($agent, 'refund', toolSearchVector(1.0, 1)))
        ->mapWithKeys(fn (ToolSearchHitData $hit): array => [$hit->entry->name => $hit->channels]);

    expect($channels['issue_refund'])->toBe('bm25+vector')
        ->and($channels['mountain_weather'])->toBe('vector');
});
