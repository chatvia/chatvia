<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Data\ToolSearchHitData;
use Domain\Integration\Tool\Enums\ToolSearchPool;
use Domain\Integration\Tool\Search\ToolHybridSearch;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Pgvector\Laravel\Vector;

/**
 * Runs the real six-channel RRF query against Postgres — the SQL (CTE shape,
 * bind order, LATERAL slug pick) is the unit under test, so nothing is mocked.
 * Preset reachability comes from the precomputed reachable-set CTEs: a catalog
 * tool surfaces iff one of the agent's attached servers advertises it with an
 * unblocked membership; an always-on connector's tools ship on every turn, so
 * they stay out of recall. Tools and functions of another agent, another
 * workspace, a disabled server or an unattached function never surface, even
 * when closest to the query. A function's create-time embed job writes
 * vectors, which each test replaces with its own.
 */

/**
 * A vector of the install's size with every value zero.
 *
 * @return list<float>
 */
function toolSearchZeroVector(): array
{
    return array_fill(0, app(EmbeddingStorage::class)->dimensions(), 0.0);
}

/** @return list<ToolSearchHitData> */
function toolSearchForLead(Agent $agent): array
{
    return app(ToolHybridSearch::class)->search($agent, 'capture a new lead', toolSearchZeroVector());
}

function toolSearchCatalogTool(AgentMcpServer $server, string $name, McpToolPermission $permission = McpToolPermission::Allow): ConnectorTool
{
    $tool = ConnectorTool::factory()->create([
        'connector_slug' => $server->connector_slug,
        'name' => $name,
        'embedding' => new Vector(toolSearchZeroVector()),
    ]);

    McpServerTool::create([
        'mcp_server_id' => $server->id,
        'connector_tool_id' => $tool->id,
        'permission' => $permission,
    ]);

    return $tool;
}

it('surfaces a preset catalog tool only through an attached server with an unblocked membership, never from an always-on one', function (): void {
    $agent = Agent::factory()->create();
    $attach = fn (string $slug, McpToolPermission $permission = McpToolPermission::Allow, bool $alwaysAvailable = false): ConnectorTool => toolSearchCatalogTool(
        AgentMcpServer::factory()->preset($slug)->attachedTo($agent, $alwaysAvailable)->create(),
        'capture_lead',
        $permission,
    );
    $attach('leads');
    $attach('notion', McpToolPermission::Blocked);
    $attach('tutor', alwaysAvailable: true);

    $results = toolSearchForLead($agent);

    expect(array_map(fn (ToolSearchHitData $hit): string => $hit->entry->name, $results))->toBe(['mcp__leads__capture_lead'])
        ->and($results[0]->pool)->toBe(ToolSearchPool::McpPreset)
        ->and(toolSearchForLead(Agent::factory()->create()))->toBe([]);
});

/** A unit vector at similarity `$similarity` to the query (axis 0), leaning on axis `$axis`. */
function toolSearchVector(float $similarity, int $axis): array
{
    $vector = toolSearchZeroVector();
    $vector[0] = $similarity;
    $vector[$axis] = sqrt(1 - $similarity ** 2);

    return $vector;
}

function toolSearchCustomServer(Agent $agent, string $slug, bool $enabled = true): AgentMcpServer
{
    return AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => $slug, 'is_enabled' => $enabled]);
}

function toolSearchCustomTool(AgentMcpServer $server, string $name, array $vector, McpToolPermission $permission = McpToolPermission::Allow): void
{
    McpTool::factory()->for($server, 'mcpServer')->read()->create([
        'name' => $name,
        'description' => "Tool {$name}",
        'permission' => $permission,
    ])->forceFill([
        'embedding' => new Vector($vector),
        'search_text' => "Tool {$name}",
    ])->save();
}

function toolSearchFunction(int $workspaceId, string $name, array $vector, string $description, bool $enabled = true): AgentFunction
{
    $function = AgentFunction::factory()->create([
        'workspace_id' => $workspaceId,
        'internal_name' => $name,
        'description' => $description,
        'webhook_method' => FunctionWebhookMethod::Get,
        'is_enabled' => $enabled,
    ]);
    $function->forceFill([
        'embedding' => new Vector($vector),
        'search_text' => $name.' '.$description,
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

    toolSearchFunction($agent->workspace_id, 'unattached_fn', toolSearchVector(1.0, 1), 'Unattached helper.');
    $other = Agent::factory()->create();
    $other->functions()->attach(toolSearchFunction($other->workspace_id, 'their_fn', toolSearchVector(1.0, 1), 'Their helper.')->id);

    expect(toolSearchNames($agent, 'zzz'))->toBe(['near_fn', 'far_fn']);
});
