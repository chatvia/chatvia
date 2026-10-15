<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Membership\Team\Services\TeamService;

/*
|--------------------------------------------------------------------------
| Public API — conversations
|--------------------------------------------------------------------------
|
| A conversation answers in the `{data}` envelope like every other record,
| and its messages carry their public id, never the integer key.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->headers = ['Authorization' => 'Bearer '.$this->owner->createToken('account')->plainTextToken, 'Accept' => 'application/json'];

    $session = Session::factory()->create(['agent_id' => $this->agent->id]);
    $this->conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $this->agent->id, 'is_visible' => true]);
});

it('wraps a conversation in the data envelope with its messages by public id', function (): void {
    $question = Message::factory()->create(['chat_conversation_id' => $this->conversation->id, 'role' => 'user', 'content' => 'When do you open?']);
    $answer = Message::factory()->create(['chat_conversation_id' => $this->conversation->id, 'role' => 'assistant', 'content' => 'At nine.']);

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/agents/{$this->agent->external_id}/conversations/{$this->conversation->external_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->conversation->external_id)
        ->assertJsonPath('data.messages.0.id', $question->external_id)
        ->assertJsonPath('data.messages.0.role', 'user')
        ->assertJsonPath('data.messages.1.id', $answer->external_id)
        ->assertJsonPath('data.messages.1.content', 'At nine.');
});

it('404s a conversation id that is not a UUID', function (): void {
    $this->withHeaders($this->headers)
        ->getJson("/api/v1/agents/{$this->agent->external_id}/conversations/{$this->conversation->id}")
        ->assertNotFound();
});
