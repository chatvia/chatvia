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
    $waiting = Conversation::factory()->forAgent($agent)->visible()->handedOff(HandoffStatus::Pending)->create();
    Conversation::factory()->forAgent($agent)->visible()->create(['handoff_status' => HandoffStatus::Ai]);

    $this->actingAs($owner)
        ->getJson(route('agents.handoff.pending', ['agent' => $agent->external_id]))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('pending.0.conversation_id', $waiting->external_id);
});
