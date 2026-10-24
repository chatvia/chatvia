<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Switching a capability off detaches its built-in server from the agent.
 */
it('disables a capability by detaching it', function (): void {
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Support Bot');
    $charts = AgentMcpServer::factory()->forOwner($owner)->builtin('charts')->attachedTo($agent)->create();

    $this->actingAs($owner)
        ->deleteJson(route('agents.capabilities.disable', ['agent' => $agent->external_id, 'slug' => 'charts']))
        ->assertOk();

    expect($agent->mcpServers()->whereKey($charts->id)->exists())->toBeFalse();
});
