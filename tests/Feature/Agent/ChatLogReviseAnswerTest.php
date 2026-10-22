<?php

/**
 * Revising an answer in the chat log writes a Q&A entry, so the route needs
 * the Q&A training permission on top of reading the chat log.
 */

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['team_id' => ownerTeam($this->owner)->id]);

    $session = Session::factory()->create(['agent_id' => $this->agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $this->agent->id]);
    $this->reply = Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversation->id]);

    $this->payload = [
        'message_id' => (string) $this->reply->id,
        'user_message' => 'How do I reset my password?',
        'expected_response' => 'Open Settings, then Security, then choose Reset password.',
    ];
});

it('refuses a teammate who may read the chat log but not train Q&A, and writes nothing', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.chat.view']);

    $this->actingAs($teammate)
        ->postJson(route('agents.logs.revise-answer', $this->agent), $this->payload)
        ->assertForbidden();

    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->exists())->toBeFalse();
});

it('saves the revision as a Q&A for a teammate with both permissions', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.sources.train.qa']);

    $this->actingAs($teammate)
        ->postJson(route('agents.logs.revise-answer', $this->agent), $this->payload)
        ->assertSuccessful();

    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->exists())->toBeTrue();
});
