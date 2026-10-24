<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;

/**
 * The live-chat waiting list: the agent's conversations waiting for an
 * operator, and none the AI is still answering.
 */
it('lists the conversations waiting for an operator', function (): void {
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Support Bot');
    $waiting = Conversation::factory()->withoutTitle()->create(['agent_id' => $agent->id, 'handoff_status' => HandoffStatus::Pending, 'is_visible' => true]);
    Conversation::factory()->withoutTitle()->create(['agent_id' => $agent->id, 'handoff_status' => HandoffStatus::Ai, 'is_visible' => true]);

    $this->actingAs($owner)
        ->getJson(route('agents.handoff.pending', ['agent' => $agent->external_id]))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('pending.0.conversation_id', $waiting->external_id);
});
