<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * An operator takes a waiting conversation over from the AI. One another
 * operator holds is a conflict, and one of another agent of the workspace is
 * a 404.
 */
beforeEach(function (): void {
    Event::fake([HandedOff::class]);
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
});

function thcTakeover(Agent $agent, Conversation $conversation): string
{
    return route('agents.handoff.takeover', ['agent' => $agent->external_id, 'conversation' => $conversation->external_id]);
}

it('takes a waiting conversation over for the operator', function (): void {
    $conversation = Conversation::factory()->forAgent($this->agent)->visible()->handedOff(HandoffStatus::Pending)->create();

    $this->actingAs($this->owner)
        ->postJson(thcTakeover($this->agent, $conversation))
        ->assertSuccessful()
        ->assertJsonPath('handoff_status', HandoffStatus::Human->value);

    expect($conversation->fresh())
        ->handoff_status->toBe(HandoffStatus::Human)
        ->handoff_user_id->toBe($this->owner->id);
});

it('reports a conflict when another operator already holds the conversation', function (): void {
    $colleague = User::factory()->employee($this->owner)->create();
    $conversation = Conversation::factory()->forAgent($this->agent)->visible()->handedOff(HandoffStatus::Human, $colleague)->create();

    $this->actingAs($this->owner)
        ->postJson(thcTakeover($this->agent, $conversation))
        ->assertConflict();

    expect($conversation->fresh()->handoff_user_id)->toBe($colleague->id);
});

it('404s a conversation of another agent of the workspace', function (): void {
    $foreign = Conversation::factory()->forAgent(makeTeamAgent($this->owner, 'Other Bot'))->visible()->handedOff(HandoffStatus::Pending)->create();

    $this->actingAs($this->owner)
        ->postJson(thcTakeover($this->agent, $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->handoff_status)->toBe(HandoffStatus::Pending);
});
