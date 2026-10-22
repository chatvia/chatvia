<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = makeTeamAgent($this->user, 'Support Bot');
    $this->server = $this->user->workspace()->mcpServers()->create([
        'category' => 'custom',
        'name' => 'Weather',
        'slug' => 'weather',
        'url' => 'https://mcp.example.com/weather',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
});

it('lists accessible agents as available when none are attached', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('integrations.connectors.agents.index', ['slug' => $this->server->slug]))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available')
        ->assertJsonPath('available.0.id', $this->agent->external_id);
});

it('attaches a connector to an agent', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('integrations.connectors.agents.attach', [
            'slug' => $this->server->slug,
            'agent' => $this->agent->external_id,
        ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'attached')
        ->assertJsonPath('attached.0.id', $this->agent->external_id);

    expect($this->server->agents()->whereKey($this->agent->id)->exists())->toBeTrue();
});

it('detaches a connector from an agent', function (): void {
    $this->agent->mcpServers()->attach($this->server->id);

    $this->actingAs($this->user)
        ->deleteJson(route('integrations.connectors.agents.detach', [
            'slug' => $this->server->slug,
            'agent' => $this->agent->external_id,
        ]))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available');

    expect($this->server->agents()->whereKey($this->agent->id)->exists())->toBeFalse();
});

it('lists and attaches agents from another team in the same account', function (): void {
    $otherTeam = app(TeamService::class)->createDefaultTeam($this->user);
    $otherTeamAgent = makeTeamAgent($this->user, 'Sales Bot', $otherTeam);

    $this->actingAs($this->user)
        ->getJson(route('integrations.connectors.agents.index', ['slug' => $this->server->slug]))
        ->assertOk()
        ->assertJsonCount(2, 'available');

    $this->actingAs($this->user)
        ->postJson(route('integrations.connectors.agents.attach', [
            'slug' => $this->server->slug,
            'agent' => $otherTeamAgent->external_id,
        ]))
        ->assertSuccessful();

    expect($this->server->agents()->whereKey($otherTeamAgent->id)->exists())->toBeTrue();
});

it('reports the attached agent count via the connectors data endpoint', function (): void {
    $this->agent->mcpServers()->attach($this->server->id);

    $this->actingAs($this->user)
        ->getJson(route('integrations.connectors.data'))
        ->assertOk()
        ->assertJsonPath('custom.0.server.agents_count', 1);
});

it('answers 404 for another workspace\'s agent on attach and detach, and leaves it alone', function (): void {
    $strangerAgent = createAgent(User::factory()->create(), ['name' => 'Not Yours']);
    $strangerServer = AgentMcpServer::factory()->create(['workspace_id' => $strangerAgent->workspace_id, 'slug' => 'weather']);
    $strangerAgent->mcpServers()->attach($strangerServer->id);
    $route = ['slug' => $this->server->slug, 'agent' => $strangerAgent->external_id];

    $this->actingAs($this->user)->postJson(route('integrations.connectors.agents.attach', $route))->assertNotFound();
    $this->actingAs($this->user)->deleteJson(route('integrations.connectors.agents.detach', $route))->assertNotFound();

    expect($this->server->agents()->whereKey($strangerAgent->id)->exists())->toBeFalse()
        ->and($strangerServer->agents()->whereKey($strangerAgent->id)->exists())->toBeTrue();
});
