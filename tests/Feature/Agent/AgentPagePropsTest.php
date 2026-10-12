<?php

use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia;

/**
 * Dashboard agent pages send the agent as AgentResourceData, never as the raw
 * Eloquent model (which carries owner ids, metadata and model settings).
 */
beforeEach(function (): void {
    // Agent::factory()->create() gives the owner an active subscription so the agent route
    // group's subscription gate lets the request reach the controller.
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('sends the agent as resource data, not the raw model', function (string $routeName): void {
    $this->actingAs($this->owner)
        ->get(route($routeName, $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('agent.external_id', $this->agent->external_id)
            ->where('agent.is_public', $this->agent->is_public)
            ->missing('agent.workspace_id')
            ->missing('agent.team_id')
            ->missing('agent.metadata')
            ->missing('agent.auth_secret')
            ->etc()
        );
})->with([
    'identity' => 'agents.identity',
    'rate limiting' => 'agents.security.rate-limit',
    'chatters' => 'agents.chatters.index',
]);
