<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;

/**
 * Handing a live chat back to the AI by the operator who holds it.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
    $this->conversation = Conversation::factory()->forAgent($this->agent)->visible()->handedOff(HandoffStatus::Human, $this->owner)->create();
    $this->url = route('agents.handoff.return', ['agent' => $this->agent->external_id, 'conversation' => $this->conversation->external_id]);
});

it('hands the conversation back to the AI', function (): void {
    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertSuccessful()
        ->assertJsonPath('handoff_status', HandoffStatus::Ai->value);

    expect($this->conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai);
});
