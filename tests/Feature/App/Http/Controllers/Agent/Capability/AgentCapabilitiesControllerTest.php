<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Inertia\Testing\AssertableInertia;

/**
 * An agent's Capabilities page lists the built-in capabilities it can switch
 * on (charts and forms; leads are part of forms), in catalog order, each with
 * whether it is on for the agent.
 */
it('lists charts and forms in catalog order, with whether each is on', function (): void {
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Support Bot');
    AgentMcpServer::factory()->forOwner($owner)->builtin('charts')->create();

    $this->actingAs($owner)
        ->get(route('agents.capabilities', $agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('agents/Capabilities/index')
            ->has('capabilities', 2)
            // Ordered by catalog priority: charts (30), forms (40).
            ->where('capabilities.0.connector.slug', 'charts')
            ->where('capabilities.0.enabled', false)
            ->where('capabilities.1.connector.slug', 'forms'));
});
