<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/**
 * The pool cache (ToolRegistry, 1h TTL) must not outlive an attach/detach —
 * pivot writes fire no Eloquent events, so the Agent attach/detach methods
 * bust the cache themselves. Without that, enabling a connector for an agent
 * takes up to an hour to reach any surface served by the cached pool.
 */
function registryCacheAgent(): Agent
{
    $user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($user);

    return Agent::create([
        'user_id' => $user->id,
        'team_id' => $user->primaryTeam()->id,
        'name' => 'Registry Cache Agent',
        'external_id' => Str::random(14),
        'slug' => Str::lower(Str::random(12)),
        'is_public' => false,
        'source' => 'api',
    ]);
}

/**
 * @param  array<int, ToolEntry>  $pool
 * @return array<int, string>
 */
function registryPoolNames(array $pool): array
{
    return array_map(static fn (ToolEntry $entry): string => $entry->name, $pool);
}

it('reflects a connector attach and detach immediately despite the warm pool cache', function (): void {
    $agent = registryCacheAgent();

    $server = AgentMcpServer::create([
        'user_id' => $agent->user_id,
        'name' => 'Cache Test Server',
        'slug' => 'cachetest',
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => 'do_thing',
        'description' => 'Does a thing',
        'input_schema' => ['type' => 'object', 'properties' => []],
        'permission' => McpToolPermission::Allow,
        'fetched_at' => now(),
    ]);

    $registry = app(ToolRegistry::class);

    // Warm the cache with the pre-attach pool.
    expect(registryPoolNames($registry->for($agent)))->not->toContain('mcp__cachetest__do_thing');

    $agent->attachConnector($server);
    expect(registryPoolNames($registry->for($agent)))->toContain('mcp__cachetest__do_thing');

    $agent->detachConnector($server);
    expect(registryPoolNames($registry->for($agent)))->not->toContain('mcp__cachetest__do_thing');
});

it('reflects a function attach and detach immediately despite the warm pool cache', function (): void {
    $agent = registryCacheAgent();

    $function = new AgentFunction;
    $function->user_id = $agent->user_id;
    $function->internal_name = 'cache_write_thing';
    $function->description = 'Writes a thing';
    $function->parameters_schema = ['type' => 'object', 'properties' => []];
    $function->webhook_url = 'https://hooks.example.test/fn';
    $function->webhook_method = 'POST';
    $function->auth_type = 'none';
    $function->requires_approval = false;
    $function->is_enabled = true;
    $function->save();

    $registry = app(ToolRegistry::class);

    // Warm the cache with the pre-attach pool.
    expect(registryPoolNames($registry->for($agent)))->not->toContain('cache_write_thing');

    $agent->attachFunction($function);
    expect(registryPoolNames($registry->for($agent)))->toContain('cache_write_thing');

    $agent->detachFunction($function);
    expect(registryPoolNames($registry->for($agent)))->not->toContain('cache_write_thing');
});

it('drops the cached pool when an attached connector is deleted', function (): void {
    $agent = registryCacheAgent();

    $server = AgentMcpServer::create([
        'user_id' => $agent->user_id,
        'name' => 'Doomed Server',
        'slug' => 'doomed',
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
    McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => 'do_thing',
        'description' => 'Does a thing',
        'input_schema' => ['type' => 'object', 'properties' => []],
        'permission' => McpToolPermission::Allow,
        'fetched_at' => now(),
    ]);
    $agent->attachConnector($server);

    $registry = app(ToolRegistry::class);
    expect(registryPoolNames($registry->for($agent)))->toContain('mcp__doomed__do_thing');

    // The pivot rows go with the server (cascade), so the observer must read
    // the attached agents before the delete.
    $server->delete();

    expect(registryPoolNames($registry->for($agent)))->not->toContain('mcp__doomed__do_thing');
});

it('drops the cached pool when an attached function is deleted from Settings', function (): void {
    $agent = registryCacheAgent();
    $owner = $agent->user;

    $function = new AgentFunction;
    $function->user_id = $agent->user_id;
    $function->internal_name = 'doomed_fn';
    $function->description = 'Goes away';
    $function->parameters_schema = ['type' => 'object', 'properties' => []];
    $function->webhook_url = 'https://hooks.example.test/fn';
    $function->webhook_method = 'POST';
    $function->auth_type = 'none';
    $function->is_enabled = true;
    $function->save();
    $agent->attachFunction($function);

    $registry = app(ToolRegistry::class);
    expect(registryPoolNames($registry->for($agent)))->toContain('doomed_fn');

    test()->actingAs($owner)
        ->delete(route('integrations.functions.destroy', ['external_id' => $function->external_id]))
        ->assertRedirect();

    expect(AgentFunction::query()->whereKey($function->id)->exists())->toBeFalse()
        ->and(registryPoolNames($registry->for($agent)))->not->toContain('doomed_fn');
});
