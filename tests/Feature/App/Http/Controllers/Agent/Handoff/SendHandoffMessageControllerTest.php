<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * An operator's reply in a live chat: posted as an operator message, and only
 * by the operator who holds the conversation.
 */
beforeEach(function (): void {
    Event::fake([MessagePosted::class]);
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
});

it('posts the reply of the operator who holds the conversation', function (): void {
    $conversation = Conversation::factory()->withoutTitle()->create(['agent_id' => $this->agent->id, 'handoff_status' => HandoffStatus::Human, 'handoff_user_id' => $this->owner->id, 'is_visible' => true]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.handoff.message', ['agent' => $this->agent->external_id, 'conversation' => $conversation->external_id]), ['content' => 'Hi, I am here.'])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message.content', 'Hi, I am here.')
        ->assertJsonPath('message.role', MessageRole::Operator->value);

    Event::assertDispatched(MessagePosted::class);
});

it('refuses a reply from an operator who does not hold the conversation', function (): void {
    $conversation = Conversation::factory()->withoutTitle()->create(['agent_id' => $this->agent->id, 'handoff_status' => HandoffStatus::Pending, 'is_visible' => true]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.handoff.message', ['agent' => $this->agent->external_id, 'conversation' => $conversation->external_id]), ['content' => 'Hello'])
        ->assertForbidden()
        ->assertJsonPath('message', 'You must take over this conversation before sending messages.');

    Event::assertNotDispatched(MessagePosted::class);
});
