<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;

/**
 * Connectors belong to the workspace. A teammate with account.connectors must
 * see and manage the owner's connectors.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);

    $this->member = teammateWithPermissions($this->owner, ['account.connectors']);
});

it('shows the owner\'s connectors to a teammate', function (): void {
    $server = AgentMcpServer::factory()->forOwner($this->owner)->create(['slug' => 'crm']);

    $this->actingAs($this->member)
        ->getJson(route('integrations.connectors.data'))
        ->assertOk()
        ->assertJsonFragment(['external_id' => $server->external_id]);
});

it('lets a teammate manage an owner connector by slug instead of 404ing', function (): void {
    $server = AgentMcpServer::factory()->forOwner($this->owner)->create(['slug' => 'crm']);

    $this->actingAs($this->member)
        ->postJson(route('integrations.connectors.enable', ['slug' => 'crm']))
        ->assertOk();
    expect($server->fresh()->is_enabled)->toBeFalse();

    $this->actingAs($this->member)
        ->getJson(route('integrations.connectors.agents.index', ['slug' => 'crm']))
        ->assertOk();
});
