<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;

/**
 * The "Unanswered" data endpoint pages the agent's replies that need a look.
 * It shows visitor questions, so it needs the chat log's view permission.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $conversation = Conversation::factory()->create(['agent_id' => $this->agent->id]);
    Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Do you ship to Iceland?']);
    Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversation->id, 'has_llm_found_answer' => false]);
});

it('returns a page of replies that need a look', function (): void {
    $this->actingAs($this->owner)
        ->getJson(route('agents.analytics.unanswered.data', $this->agent))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.question', 'Do you ship to Iceland?')
        ->assertJsonPath('data.0.not_found', true);
});

it('rejects an unknown filter', function (): void {
    $this->actingAs($this->owner)
        ->getJson(route('agents.analytics.unanswered.data', [$this->agent, 'filter' => 'everything']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filter');
});

it('refuses a teammate who cannot view the chat log', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.analytics.chats']);

    $this->actingAs($teammate)
        ->getJson(route('agents.analytics.unanswered.data', $this->agent))
        ->assertForbidden();
});
