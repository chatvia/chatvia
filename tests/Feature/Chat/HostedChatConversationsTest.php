<?php

use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * The hosted chat's conversation endpoints, end to end: they resolve the
 * visitor's chat session from the browser session and act only on that
 * session's conversations.
 */
beforeEach(function (): void {
    config(['chatvia.ai.query.rewriter.enabled' => false]);
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
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id, 'content' => 'My card number is 4242']);

    $this->deleteJson("{$this->base}/conversations/{$id}")->assertOk();

    expect(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue()
        ->and($message->fresh()->content)->toBeNull();
});

it('validates the rating value', function (): void {
    $this->patchJson("{$this->base}/messages/feedback", ['conversation_id' => (string) Str::uuid(), 'message_id' => (string) Str::uuid(), 'feedback' => 'great'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['feedback']);
});

it('records the visitor\'s rating of a reply in their conversation', function (): void {
    $id = $this->postJson("{$this->base}/conversations/create", ['message' => 'Hi'])->json('id');
    $reply = Message::factory()->fromAssistant()->create([
        'chat_conversation_id' => Conversation::query()->firstWhere('external_id', $id)->id,
        'feedback' => null,
    ]);

    $this->patchJson("{$this->base}/messages/feedback", ['conversation_id' => $id, 'message_id' => $reply->external_id, 'feedback' => 'positive'])
        ->assertOk();

    expect($reply->fresh()->feedback)->toBe(MessageFeedback::Positive);
});

it('does not let another visitor rate, delete or erase the visitor\'s messages and conversations', function (): void {
    $id = $this->postJson("{$this->base}/conversations/create", ['message' => 'Where is my order?'])->json('id');
    $conversation = Conversation::query()->firstWhere('external_id', $id);
    $reply = Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversation->id, 'feedback' => null]);
    $scope = app(HostedChatSession::class)->scope($this->agent, $id);
    $firstMessageId = app(ChatMemory::class)->withScope($scope)->getMessagesForConversation()[0]->id;

    // A fresh browser session is a different visitor.
    $this->flushSession();

    $this->patchJson("{$this->base}/messages/feedback", ['conversation_id' => $id, 'message_id' => $reply->external_id, 'feedback' => 'negative'])->assertOk();
    $this->deleteJson("{$this->base}/messages", ['conversation_id' => $id, 'message_id' => $firstMessageId])->assertOk();
    $this->deleteJson("{$this->base}/conversations")->assertOk();

    expect($reply->fresh()->feedback)->toBeNull()
        ->and(array_column(app(ChatMemory::class)->withScope($scope)->getMessagesForConversation(), 'content'))->toBe(['Where is my order?'])
        ->and($conversation->fresh()->trashed())->toBeFalse();
});
