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
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
});

function thcConversation(Agent $agent, HandoffStatus $status = HandoffStatus::Pending, ?int $heldBy = null): Conversation
{
    return Conversation::factory()->withoutTitle()->create(['agent_id' => $agent->id, 'handoff_status' => $status, 'handoff_user_id' => $heldBy, 'is_visible' => true]);
}

function thcTakeover(Agent $agent, Conversation $conversation): string
{
    return route('agents.handoff.takeover', ['agent' => $agent->external_id, 'conversation' => $conversation->external_id]);
}

it('takes a waiting conversation over for the operator', function (): void {
    $conversation = thcConversation($this->agent);

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
    $conversation = thcConversation($this->agent, HandoffStatus::Human, $colleague->id);

    $this->actingAs($this->owner)
        ->postJson(thcTakeover($this->agent, $conversation))
        ->assertConflict();

    expect($conversation->fresh()->handoff_user_id)->toBe($colleague->id);
});

it('404s a conversation of another agent of the workspace', function (): void {
    $foreign = thcConversation(makeTeamAgent($this->owner, 'Other Bot'));

    $this->actingAs($this->owner)
        ->postJson(thcTakeover($this->agent, $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->handoff_status)->toBe(HandoffStatus::Pending);
});
