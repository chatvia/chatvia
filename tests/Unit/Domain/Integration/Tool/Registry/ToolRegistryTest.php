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

/**
 * The registry compiles an agent's whole tool pool: the own `mcp_tools` rows
 * of its attached custom servers, the shared catalog tools its attached
 * preset servers reach through their memberships, and its attached webhook
 * functions. A central-catalog server is read from its memberships only,
 * never from stale own tool rows. The tool access policy relies on the
 * compile step to keep blocked tools, disabled servers and functions, and
 * anything not attached to the agent (the same workspace's other servers,
 * another agent's, the global built-in catalog) away from the model. The pool
 * is cached per agent; pivot writes fire no Eloquent events, so attaching or
 * detaching a connector or function drops the cached pool itself: the next
 * turn sees the change at once.
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

it('compiles the attached custom tools, preset catalog tools and functions into one pool, a preset from its memberships only', function (): void {
    $agent = Agent::factory()->create();
    $custom = AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => 'crm']);
    $ownTool = McpTool::factory()->for($custom, 'mcpServer')->read()->create([
        'name' => 'find_contact',
        'description' => 'Find a contact',
        'input_schema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string']]],
    ]);
    $preset = AgentMcpServer::factory()->preset('notion')->attachedTo($agent)->create();
    $catalogTool = toolRegistryCatalogTool($preset, 'create_page');
    McpTool::factory()->for($preset, 'mcpServer')->allowed()->create(['name' => 'stale_own_row']);
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

it('keeps blocked tools, disabled servers and functions, and anything not attached to the agent out of the pool', function (): void {
    app(SyncBuiltinConnectorsAction::class)->execute(new SyncBuiltinConnectorsData(queueEmbedding: true));
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $custom = AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => 'crm']);
    McpTool::factory()->for($custom, 'mcpServer')->create(['name' => 'list_deals']);
    McpTool::factory()->for($custom, 'mcpServer')->blocked()->create(['name' => 'delete_everything']);
    $preset = AgentMcpServer::factory()->preset('notion')->attachedTo($agent)->create();
    toolRegistryCatalogTool($preset, 'search', McpToolPermission::Approval);
    toolRegistryCatalogTool($preset, 'delete_page', McpToolPermission::Blocked);
    McpTool::factory()->for(AgentMcpServer::factory()->disabled()->attachedTo($agent)->create(['slug' => 'off']), 'mcpServer')->allowed()->create(['name' => 'switched_off']);
    toolRegistryCatalogTool(AgentMcpServer::factory()->preset('linear')->disabled()->attachedTo($agent)->create(), 'list_issues');
    AgentFunction::factory()->disabled()->attachedTo($agent)->create(['internal_name' => 'refund_order']);
    McpTool::factory()->for(AgentMcpServer::factory()->forOwner($owner)->create(['slug' => 'unattached']), 'mcpServer')->allowed()->create();
    AgentFunction::factory()->forOwner($owner)->create(['internal_name' => 'lookup_order']);
    $sibling = Agent::factory()->forOwner($owner)->create();
    McpTool::factory()->for(AgentMcpServer::factory()->attachedTo($sibling)->create(['slug' => 'billing']), 'mcpServer')->allowed()->create();
    AgentFunction::factory()->attachedTo($sibling)->create(['internal_name' => 'sibling_order']);

    expect(toolRegistryPoolOf($agent))->toBe(['mcp__crm__list_deals', 'mcp__notion__search'])
        ->and(toolRegistryPoolOf($sibling))->toHaveCount(2);
});

it('reflects a connector or function attach and detach at once, despite the cached pool', function (): void {
    $agent = Agent::factory()->create();
    $server = AgentMcpServer::factory()->forOwner($agent->workspace->owner)->create(['slug' => 'crm']);
    McpTool::factory()->for($server, 'mcpServer')->allowed()->create(['name' => 'list_deals']);
    $function = AgentFunction::factory()->forOwner($agent->workspace->owner)->create(['internal_name' => 'lookup_order']);
    $pools = [toolRegistryPoolOf($agent)];

    $agent->attachConnector($server);
    $pools[] = toolRegistryPoolOf($agent);
    $agent->attachFunction($function);
    $pools[] = toolRegistryPoolOf($agent);
    $agent->detachConnector($server);
    $pools[] = toolRegistryPoolOf($agent);
    $agent->detachFunction($function);
    $pools[] = toolRegistryPoolOf($agent);

    expect($pools)->toBe([
        [],
        ['mcp__crm__list_deals'],
        ['mcp__crm__list_deals', 'lookup_order'],
        ['lookup_order'],
        [],
    ]);
});
