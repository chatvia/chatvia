<?php

use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia;

/**
 * The agent's Blocked page; its list loads from ListAgentBlocksController.
 */
it('opens the Blocked page', function (): void {
    $agent = Agent::factory()->create();

    $this->actingAs($agent->workspace->owner)
        ->get(route('agents.security.blocked', $agent->external_id))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('agents/security/Blocked'));
});
