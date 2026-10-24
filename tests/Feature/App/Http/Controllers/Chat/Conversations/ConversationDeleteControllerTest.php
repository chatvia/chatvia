<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The visitor erases a conversation of their own chat session; another
 * visitor's conversation is not found and stays.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->id = HostedChatVisitor::startConversation($this->agent);
    $this->url = "/chat/{$this->agent->slug}/conversations/{$this->id}";
});

it('erases the visitor\'s conversation', function (): void {
    $conversation = Conversation::query()->firstWhere('external_id', $this->id);
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id, 'content' => 'My card number is 4242']);

    $this->deleteJson($this->url)->assertOk();

    expect(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue()
        ->and($message->fresh()->content)->toBeNull();
});

it('answers 404 for a conversation of another visitor and keeps it', function (): void {
    // A fresh browser session is a different visitor.
    $this->flushSession();

    $this->deleteJson($this->url)->assertNotFound();

    expect(Conversation::query()->firstWhere('external_id', $this->id)->trashed())->toBeFalse();
});
