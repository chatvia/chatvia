<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\DB;

/**
 * Turning a connector on for an agent from its Connectors page: the
 * connector is attached with "always on" switched on, so its tools go out on
 * every turn until the agent turns that off.
 */
it('attaches a workspace connector to the agent with always on switched on', function (): void {
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Tutor Bot');
    $server = AgentMcpServer::factory()->forOwner($owner)->create(['slug' => 'hejly-tutor']);

    $this->actingAs($owner)
        ->postJson(route('agents.sources.connectors.attach', ['agent' => $agent->external_id, 'slug' => 'hejly-tutor']))
        ->assertCreated();

    expect(DB::table('agent_mcp_server')->where('agent_id', $agent->id)->where('mcp_server_id', $server->id)->value('always_available'))->toBeTrue();
});

it('404s for a connector of another workspace', function (): void {
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Tutor Bot');
    AgentMcpServer::factory()->forOwner(User::factory()->create())->create(['slug' => 'elsewhere']);

    $this->actingAs($owner)
        ->postJson(route('agents.sources.connectors.attach', ['agent' => $agent->external_id, 'slug' => 'elsewhere']))
        ->assertNotFound();

    expect($agent->mcpServers()->count())->toBe(0);
});
