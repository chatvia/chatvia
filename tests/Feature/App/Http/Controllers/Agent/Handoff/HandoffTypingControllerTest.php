<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\TypingStarted;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * The operator's typing indicator in a live chat: announced as the agent's
 * side typing, and only for the operator who holds the conversation.
 */
beforeEach(function (): void {
    Event::fake([TypingStarted::class]);
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
    $this->conversation = Conversation::factory()->withoutTitle()->create(['agent_id' => $this->agent->id, 'handoff_status' => HandoffStatus::Human, 'handoff_user_id' => $this->owner->id, 'is_visible' => true]);
    $this->url = route('agents.handoff.typing', ['agent' => $this->agent->external_id, 'conversation' => $this->conversation->external_id]);
});

it('announces that the operator is typing', function (): void {
    $this->actingAs($this->owner)->postJson($this->url, ['is_typing' => true])->assertSuccessful();

    Event::assertDispatched(TypingStarted::class, fn (TypingStarted $event): bool => $event->typerType === 'agent');
});

it('refuses a teammate who does not hold the conversation', function (): void {
    $this->actingAs(teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover']))
        ->postJson($this->url, ['is_typing' => true])
        ->assertForbidden();

    Event::assertNotDispatched(TypingStarted::class);
});
