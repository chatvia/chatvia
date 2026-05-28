<?php

use App\Auth\Models\User;
use App\Teams\Services\TeamService;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = makeTeamAgent($this->user, 'Support Bot');
    $this->server = $this->user->mcpServers()->create([
        'name' => 'Weather',
        'slug' => 'weather',
        'url' => 'https://mcp.example.com/weather',
        'auth_type' => 'none',
        'enabled' => true,
    ]);
});

it('lists accessible agents as available when none are attached', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('settings.connectors.agents.index', ['slug' => $this->server->slug]))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available')
        ->assertJsonPath('available.0.id', $this->agent->external_id);
});

it('attaches a connector to an agent', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('settings.connectors.agents.attach', [
            'slug' => $this->server->slug,
            'agent' => $this->agent->external_id,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'attached')
        ->assertJsonPath('attached.0.id', $this->agent->external_id);

    expect($this->server->agents()->whereKey($this->agent->id)->exists())->toBeTrue();
});

it('detaches a connector from an agent', function (): void {
    $this->agent->mcpServers()->attach($this->server->id);

    $this->actingAs($this->user)
        ->deleteJson(route('settings.connectors.agents.detach', [
            'slug' => $this->server->slug,
            'agent' => $this->agent->external_id,
        ]))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available');

    expect($this->server->agents()->whereKey($this->agent->id)->exists())->toBeFalse();
});

it('reports the attached agent count via the connectors data endpoint', function (): void {
    $this->agent->mcpServers()->attach($this->server->id);

    $this->actingAs($this->user)
        ->getJson(route('settings.connectors.data'))
        ->assertOk()
        ->assertJsonPath('custom.0.server.agents_count', 1);
});
