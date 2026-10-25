<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's conversations through the public API: opening one on the `api`
 * surface (optionally for one of the workspace's chatters), showing one in
 * the `{data}` envelope with its messages by public id, and deleting one.
 * What opening and deleting store is tested with their Actions.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/conversations";
});

it('opens a conversation on the api surface with the title it was given, hidden for a day', function (): void {
    $this->freezeSecond();

    $id = $this->withToken($this->token)
        ->postJson($this->uri, ['title' => 'Support run'])
        ->assertCreated()
        ->json('data.id');

    $conversation = Conversation::query()->where('external_id', $id)->sole();

    expect($conversation->agent_id)->toBe($this->agent->id)
        ->and($conversation->source)->toBe(ConversationSource::Api)
        ->and($conversation->title)->toBe('Support run')
        ->and($conversation->expires_at->equalTo(now()->addDay()))->toBeTrue();
});

it('opens the conversation for the chatter it names', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();

    $id = $this->withToken($this->token)
        ->postJson($this->uri, ['chatter_id' => $chatter->external_id])
        ->assertCreated()
        ->json('data.id');

    expect(Conversation::query()->where('external_id', $id)->sole()->session->chatter_id)->toBe($chatter->id);
});

it('rejects a chatter of the workspace who has no access to the agent as a field error', function (): void {
    $chatter = Chatter::factory()->ofWorkspace($this->owner)->create();

    $this->withToken($this->token)
        ->postJson($this->uri, ['chatter_id' => $chatter->external_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chatter_id']);

    expect(Conversation::query()->where('agent_id', $this->agent->id)->exists())->toBeFalse();
});

it("rejects another workspace's chatter as a field error", function (): void {
    $this->withToken($this->token)
        ->postJson($this->uri, ['chatter_id' => Chatter::factory()->create()->external_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chatter_id']);
});

it('wraps a conversation in the data envelope with its messages by public id', function (): void {
    $conversation = Conversation::factory()->create(['agent_id' => $this->agent->id, 'is_visible' => true]);
    $question = Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'When do you open?']);
    $answer = Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'At nine.']);

    $this->withToken($this->token)
        ->getJson("{$this->uri}/{$conversation->external_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $conversation->external_id)
        ->assertJsonPath('data.messages.0.id', $question->external_id)
        ->assertJsonPath('data.messages.0.role', 'user')
        ->assertJsonPath('data.messages.1.id', $answer->external_id)
        ->assertJsonPath('data.messages.1.content', 'At nine.');
});

it('deletes a conversation, records who deleted it and stops showing it', function (): void {
    $conversation = Conversation::factory()->create(['agent_id' => $this->agent->id, 'is_visible' => true]);
    $uri = "{$this->uri}/{$conversation->external_id}";

    $this->withToken($this->token)->deleteJson($uri)->assertNoContent();

    expect(Conversation::withTrashed()->findOrFail($conversation->id)->deleted_by)->toBe((string) $this->owner->id);

    $this->withToken($this->token)->getJson($uri)->assertNotFound();
});
