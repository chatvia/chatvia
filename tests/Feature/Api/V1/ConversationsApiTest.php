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
    $this->token = $this->owner->createToken('account')->plainTextToken;

    $session = Session::factory()->create(['agent_id' => $this->agent->id]);
    $this->conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $this->agent->id, 'is_visible' => true]);
});

it('wraps a conversation in the data envelope with its messages by public id', function (): void {
    $question = Message::factory()->create(['chat_conversation_id' => $this->conversation->id, 'role' => 'user', 'content' => 'When do you open?']);
    $answer = Message::factory()->create(['chat_conversation_id' => $this->conversation->id, 'role' => 'assistant', 'content' => 'At nine.']);

    $this->withToken($this->token)
        ->getJson("/api/v1/agents/{$this->agent->external_id}/conversations/{$this->conversation->external_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->conversation->external_id)
        ->assertJsonPath('data.messages.0.id', $question->external_id)
        ->assertJsonPath('data.messages.0.role', 'user')
        ->assertJsonPath('data.messages.1.id', $answer->external_id)
        ->assertJsonPath('data.messages.1.content', 'At nine.');
});

it('deletes a conversation: wipes its messages, records who deleted it and stops showing it', function (): void {
    $message = Message::factory()->create(['chat_conversation_id' => $this->conversation->id, 'role' => 'user', 'content' => 'My card number is 4242']);
    $uri = "/api/v1/agents/{$this->agent->external_id}/conversations/{$this->conversation->external_id}";

    $this->withToken($this->token)->deleteJson($uri)->assertNoContent();

    $deleted = Conversation::withTrashed()->findOrFail($this->conversation->id);

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->deleted_by)->toBe((string) $this->owner->id)
        ->and($message->fresh()->content)->toBeNull();

    $this->withToken($this->token)->getJson($uri)->assertNotFound();
});
