<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;

/**
 * Handing a live chat back to the AI: only the operator who holds it can; for
 * anyone else it is a conflict and the conversation stays with its operator.
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

it('reports a conflict to a teammate who does not hold the conversation', function (): void {
    $this->actingAs(teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover']))
        ->postJson($this->url)
        ->assertConflict();

    expect($this->conversation->fresh())
        ->handoff_status->toBe(HandoffStatus::Human)
        ->handoff_user_id->toBe($this->owner->id);
});
