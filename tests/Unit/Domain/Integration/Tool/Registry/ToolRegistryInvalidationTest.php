<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * The pool cache (ToolRegistry, 1h TTL) must not outlive an attach/detach —
 * pivot writes fire no Eloquent events, so the Agent attach/detach methods
 * bust the cache themselves. Without that, enabling a connector for an agent
 * takes up to an hour to reach any surface served by the cached pool.
 */

/**
 * @param  array<int, ToolEntry>  $pool
 * @return array<int, string>
 */
function registryPoolNames(array $pool): array
{
    return array_map(static fn (ToolEntry $entry): string => $entry->name, $pool);
}

it('reflects a connector attach and detach immediately despite the warm pool cache', function (): void {
    $agent = Agent::factory()->create();

    $server = AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'slug' => 'cachetest']);
    McpTool::factory()->for($server, 'mcpServer')->allowed()->create(['name' => 'do_thing']);

    $registry = app(ToolRegistry::class);

    // Warm the cache with the pre-attach pool.
    expect(registryPoolNames($registry->for($agent)))->not->toContain('mcp__cachetest__do_thing');

    $agent->attachConnector($server);
    expect(registryPoolNames($registry->for($agent)))->toContain('mcp__cachetest__do_thing');

    $agent->detachConnector($server);
    expect(registryPoolNames($registry->for($agent)))->not->toContain('mcp__cachetest__do_thing');
});

it('reflects a function attach and detach immediately despite the warm pool cache', function (): void {
    $agent = Agent::factory()->create();

    $function = AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id, 'internal_name' => 'cache_write_thing']);

    $registry = app(ToolRegistry::class);

    // Warm the cache with the pre-attach pool.
    expect(registryPoolNames($registry->for($agent)))->not->toContain('cache_write_thing');

    $agent->attachFunction($function);
    expect(registryPoolNames($registry->for($agent)))->toContain('cache_write_thing');

    $agent->detachFunction($function);
    expect(registryPoolNames($registry->for($agent)))->not->toContain('cache_write_thing');
});

it('drops the cached pool when an attached connector is deleted', function (): void {
    $agent = Agent::factory()->create();

    $server = AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'slug' => 'doomed']);
    McpTool::factory()->for($server, 'mcpServer')->allowed()->create(['name' => 'do_thing']);
    $agent->attachConnector($server);

    $registry = app(ToolRegistry::class);
    expect(registryPoolNames($registry->for($agent)))->toContain('mcp__doomed__do_thing');

    // The pivot rows go with the server (cascade), so the observer must read
    // the attached agents before the delete.
    $server->delete();

    expect(registryPoolNames($registry->for($agent)))->not->toContain('mcp__doomed__do_thing');
});
