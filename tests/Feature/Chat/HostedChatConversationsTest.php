<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * The hosted chat's conversation endpoints, end to end: they resolve the
 * visitor's chat session from the browser session and act only on that
 * session's conversations.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array'], 'chatvia.ai.query.rewriter.enabled' => false]);
    Bus::fake();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->base = "/chat/{$this->agent->slug}";
});

it('opens a conversation from the first message and lists it after it is named', function (): void {
    $id = $this->postJson("{$this->base}/conversations/create", ['message' => 'Where is my order?'])
        ->assertOk()
        ->json('id');

    $conversation = Conversation::query()->firstWhere('external_id', $id);
    expect($conversation)->not->toBeNull();

    $this->patchJson("{$this->base}/conversations/{$id}", ['title' => 'Order status'])->assertOk();

    $this->getJson("{$this->base}/conversations")
        ->assertOk()
        ->assertJsonPath('0.id', $id)
        ->assertJsonPath('0.title', 'Order status');

    $this->getJson("{$this->base}/conversations/{$id}/messages")
        ->assertOk()
        ->assertJsonPath('0.content', 'Where is my order?')
        ->assertJsonPath('0.role', 'user');
});

it('requires a title to rename a conversation', function (): void {
    $id = $this->postJson("{$this->base}/conversations/create", ['message' => 'Hi'])->json('id');

    $this->patchJson("{$this->base}/conversations/{$id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('cannot rename or delete a conversation of another visitor', function (): void {
    $id = $this->postJson("{$this->base}/conversations/create", ['message' => 'Hi'])->json('id');

    // A fresh browser session is a different visitor.
    $this->flushSession();

    $this->patchJson("{$this->base}/conversations/{$id}", ['title' => 'Hijacked'])->assertNotFound();
    $this->deleteJson("{$this->base}/conversations/{$id}")->assertNotFound();

    expect(Conversation::query()->firstWhere('external_id', $id)->trashed())->toBeFalse();
});

it('erases the visitor\'s conversation on delete', function (): void {
    $id = $this->postJson("{$this->base}/conversations/create", ['message' => 'Hi'])->json('id');
    $conversation = Conversation::query()->firstWhere('external_id', $id);
    $message = Message::create([
        'external_id' => (string) Str::uuid(),
        'workspace_id' => $conversation->workspace_id,
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'My card number is 4242',
    ]);

    $this->deleteJson("{$this->base}/conversations/{$id}")->assertOk();

    expect(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue()
        ->and($message->fresh()->content)->toBeNull();
});

it('validates and records a rating', function (): void {
    $this->patchJson("{$this->base}/messages/feedback", ['conversation_id' => (string) Str::uuid(), 'message_id' => (string) Str::uuid(), 'feedback' => 'great'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['feedback']);

    $this->patchJson("{$this->base}/messages/feedback", ['conversation_id' => (string) Str::uuid(), 'message_id' => (string) Str::uuid(), 'feedback' => 'positive'])
        ->assertOk();
});
