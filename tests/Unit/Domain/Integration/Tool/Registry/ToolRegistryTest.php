<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Actions\Catalog\SyncBuiltinConnectorsAction;
use Domain\Integration\Mcp\Data\Catalog\SyncBuiltinConnectorsData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Data\ToolEntryData;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The registry compiles an agent's whole tool pool: the own `mcp_tools` rows
 * of its attached custom servers, the shared catalog tools its attached
 * preset servers reach through their memberships, and its attached webhook
 * functions. The pool is cached per agent, and the tool access policy relies
 * on the compile step to keep blocked tools away from the model. Pivot writes
 * fire no Eloquent events, so attaching or detaching a connector or function
 * drops the cached pool itself: the next turn sees the change at once.
 */

/** @return list<string> */
function toolRegistryPoolOf(Agent $agent): array
{
    return array_map(static fn (ToolEntryData $entry): string => $entry->name, app(ToolRegistry::class)->for($agent));
}

/** A shared catalog tool of the server's connector, reached through a membership with the given permission. */
function toolRegistryCatalogTool(AgentMcpServer $server, string $name, McpToolPermission $permission = McpToolPermission::Allow): ConnectorTool
{
    $tool = ConnectorTool::factory()->create(['connector_slug' => $server->connector_slug, 'name' => $name]);
    $server->connectorTools()->attach($tool->id, ['permission' => $permission]);

    return $tool;
}

it('compiles the attached custom tools, preset catalog tools and functions into one pool', function (): void {
    $agent = Agent::factory()->create();
    $custom = AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => 'crm']);
    $ownTool = McpTool::factory()->for($custom, 'mcpServer')->read()->create([
        'name' => 'find_contact',
        'description' => 'Find a contact',
        'input_schema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string']]],
    ]);
    $preset = AgentMcpServer::factory()->preset('notion')->attachedTo($agent)->create();
    $catalogTool = toolRegistryCatalogTool($preset, 'create_page');
    $function = AgentFunction::factory()->attachedTo($agent)->create([
        'internal_name' => 'lookup_order',
        'webhook_method' => FunctionWebhookMethod::Get,
    ]);

    $pool = collect(app(ToolRegistry::class)->for($agent))->keyBy(fn (ToolEntryData $entry): string => $entry->name);

    expect($pool->keys()->all())->toBe(['mcp__crm__find_contact', 'mcp__notion__create_page', 'lookup_order'])
        ->and($pool['mcp__crm__find_contact'])->toEqual(new ToolEntryData(
            kind: ToolKind::Mcp,
            sourceId: 'mcp_tool:'.$ownTool->id,
            name: 'mcp__crm__find_contact',
            description: 'Find a contact',
            parameters: ['type' => 'object', 'properties' => ['email' => ['type' => 'string']]],
            mutates: false,
        ))
        ->and($pool['mcp__notion__create_page']->sourceId)->toBe('connector_tool:'.$catalogTool->id)
        ->and($pool['mcp__notion__create_page']->mutates)->toBeTrue()
        ->and($pool['lookup_order']->kind)->toBe(ToolKind::Function)
        ->and($pool['lookup_order']->sourceId)->toBe('function:'.$function->id)
        ->and($pool['lookup_order']->mutates)->toBeFalse();
});

it('keeps blocked tools out of the pool, both own tools and catalog memberships', function (): void {
    $agent = Agent::factory()->create();
    $custom = AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => 'crm']);
    McpTool::factory()->for($custom, 'mcpServer')->create(['name' => 'list_deals']);
    McpTool::factory()->for($custom, 'mcpServer')->blocked()->create(['name' => 'delete_everything']);
    $preset = AgentMcpServer::factory()->preset('notion')->attachedTo($agent)->create();
    toolRegistryCatalogTool($preset, 'search', McpToolPermission::Approval);
    toolRegistryCatalogTool($preset, 'delete_page', McpToolPermission::Blocked);

    expect(toolRegistryPoolOf($agent))->toBe(['mcp__crm__list_deals', 'mcp__notion__search']);
});

it('keeps the tools of a disabled server out of the pool', function (): void {
    $agent = Agent::factory()->create();
    $custom = AgentMcpServer::factory()->disabled()->attachedTo($agent)->create(['slug' => 'crm']);
    McpTool::factory()->for($custom, 'mcpServer')->allowed()->create(['name' => 'list_deals']);
    toolRegistryCatalogTool(AgentMcpServer::factory()->preset('notion')->disabled()->attachedTo($agent)->create(), 'search');

    expect(toolRegistryPoolOf($agent))->toBe([]);
});

it('keeps a disabled function out of the pool', function (): void {
    $agent = Agent::factory()->create();
    AgentFunction::factory()->attachedTo($agent)->create(['internal_name' => 'lookup_order']);
    AgentFunction::factory()->disabled()->attachedTo($agent)->create(['internal_name' => 'refund_order']);

    expect(toolRegistryPoolOf($agent))->toBe(['lookup_order']);
});

it('offers no built-in server an agent has not attached, though the global catalog holds them', function (): void {
    app(SyncBuiltinConnectorsAction::class)->execute(new SyncBuiltinConnectorsData(queueEmbedding: true));

    expect(toolRegistryPoolOf(Agent::factory()->create()))->toBe([]);
});

it('keeps servers and functions that are not attached to the agent out of the pool', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    // The same workspace, but not attached to this agent.
    McpTool::factory()->for(AgentMcpServer::factory()->forOwner($owner)->create(['slug' => 'crm']), 'mcpServer')->allowed()->create();
    toolRegistryCatalogTool(AgentMcpServer::factory()->preset('notion')->forOwner($owner)->create(), 'search');
    AgentFunction::factory()->forOwner($owner)->create(['internal_name' => 'lookup_order']);
    // Attached to another agent of the same workspace.
    $sibling = Agent::factory()->forOwner($owner)->create();
    McpTool::factory()->for(AgentMcpServer::factory()->attachedTo($sibling)->create(['slug' => 'billing']), 'mcpServer')->allowed()->create();
    AgentFunction::factory()->attachedTo($sibling)->create(['internal_name' => 'refund_order']);

    expect(toolRegistryPoolOf($agent))->toBe([])
        ->and(toolRegistryPoolOf($sibling))->toHaveCount(2);
});

it('reads a central-catalog server from its memberships only, never from its own tool rows', function (): void {
    $agent = Agent::factory()->create();
    $preset = AgentMcpServer::factory()->preset('notion')->attachedTo($agent)->create();
    toolRegistryCatalogTool($preset, 'search');
    McpTool::factory()->for($preset, 'mcpServer')->allowed()->create(['name' => 'stale_own_row']);

    expect(toolRegistryPoolOf($agent))->toBe(['mcp__notion__search']);
});

it('serves the second call from the cache without querying the tool tables', function (): void {
    $agent = Agent::factory()->create();
    $custom = AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => 'crm']);
    McpTool::factory()->for($custom, 'mcpServer')->allowed()->create(['name' => 'list_deals']);
    toolRegistryCatalogTool(AgentMcpServer::factory()->preset('notion')->attachedTo($agent)->create(), 'search');
    AgentFunction::factory()->attachedTo($agent)->create(['internal_name' => 'lookup_order']);
    $registry = app(ToolRegistry::class);
    $first = $registry->for($agent);

    DB::enableQueryLog();
    $second = $registry->for($agent);
    $toolQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql): bool => Str::isMatch('/\b(mcp_tools|connector_tools|functions)\b/', $sql));
    DB::disableQueryLog();

    expect($first)->toHaveCount(3)
        ->and($second)->toEqual($first)
        ->and($toolQueries->all())->toBe([]);
});

it('compiles the pool again once the agent is forgotten', function (): void {
    $agent = Agent::factory()->create();
    $custom = AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => 'crm']);
    McpTool::factory()->for($custom, 'mcpServer')->allowed()->create(['name' => 'list_deals']);
    expect(toolRegistryPoolOf($agent))->toBe(['mcp__crm__list_deals']);

    // A write that fires no model events leaves the cached pool in place.
    DB::table('mcp_tools')->where('mcp_server_id', $custom->id)->delete();
    expect(toolRegistryPoolOf($agent))->toBe(['mcp__crm__list_deals']);

    app(ToolRegistry::class)->forget($agent->id);

    expect(toolRegistryPoolOf($agent))->toBe([]);
});

it('reflects a connector attach and detach at once, despite the cached pool', function (): void {
    $agent = Agent::factory()->create();
    $server = AgentMcpServer::factory()->forOwner($agent->workspace->owner)->create(['slug' => 'crm']);
    McpTool::factory()->for($server, 'mcpServer')->allowed()->create(['name' => 'list_deals']);
    expect(toolRegistryPoolOf($agent))->toBe([]);

    $agent->attachConnector($server);
    expect(toolRegistryPoolOf($agent))->toBe(['mcp__crm__list_deals']);

    $agent->detachConnector($server);
    expect(toolRegistryPoolOf($agent))->toBe([]);
});

it('reflects a function attach and detach at once, despite the cached pool', function (): void {
    $agent = Agent::factory()->create();
    $function = AgentFunction::factory()->forOwner($agent->workspace->owner)->create(['internal_name' => 'lookup_order']);
    expect(toolRegistryPoolOf($agent))->toBe([]);

    $agent->attachFunction($function);
    expect(toolRegistryPoolOf($agent))->toBe(['lookup_order']);

    $agent->detachFunction($function);
    expect(toolRegistryPoolOf($agent))->toBe([]);
});

it('drops the cached pool when an attached connector is deleted', function (): void {
    $agent = Agent::factory()->create();
    $server = AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => 'crm']);
    McpTool::factory()->for($server, 'mcpServer')->allowed()->create(['name' => 'list_deals']);
    expect(toolRegistryPoolOf($agent))->toBe(['mcp__crm__list_deals']);

    // The pivot rows go with the server (cascade), so the observer must read
    // the attached agents before the delete.
    $server->delete();

    expect(toolRegistryPoolOf($agent))->toBe([]);
});
