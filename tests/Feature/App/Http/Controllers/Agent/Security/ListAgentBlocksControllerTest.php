<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Security\Models\BlockedVisitor;

/**
 * The agent's Blocked list (Guardrails tab, Security → Blocked): the
 * workspace's visitors blocked right now, and the agent's own switched-off
 * chatters.
 */
it('lists who is blocked from the agent: the workspace\'s visitors and its own switched-off chatters', function (): void {
    $agent = Agent::factory()->create();
    $visitor = BlockedVisitor::factory()->create(['workspace_id' => $agent->workspace_id, 'reason' => 'Prompt hunting']);
    BlockedVisitor::factory()->expired()->create(['workspace_id' => $agent->workspace_id]);
    BlockedVisitor::factory()->create();

    $blocked = Chatter::factory()->grantedTo($agent)->deactivated()->create(['deactivation_reason' => 'Abuse']);
    Chatter::factory()->grantedTo($agent)->create();
    Chatter::factory()->ofWorkspace($agent->workspace->owner)->deactivated()->create();

    $this->actingAs($agent->workspace->owner)
        ->getJson(route('agents.security.blocked.list', $agent->external_id))
        ->assertOk()
        ->assertJsonCount(1, 'visitors')
        ->assertJsonPath('visitors.0.external_id', $visitor->external_id)
        ->assertJsonPath('visitors.0.reason', 'Prompt hunting')
        ->assertJsonCount(1, 'chatters')
        ->assertJsonPath('chatters.0.external_id', $blocked->external_id)
        ->assertJsonPath('chatters.0.reason', 'Abuse');
});
