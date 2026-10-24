<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Str;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The visitor rates a reply in a conversation of their own chat session; a
 * rating sent for another visitor's conversation changes nothing.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->url = "/chat/{$this->agent->slug}/messages/feedback";
});

it('validates the rating value', function (): void {
    $this->patchJson($this->url, ['conversation_id' => (string) Str::uuid(), 'message_id' => (string) Str::uuid(), 'feedback' => 'great'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['feedback']);
});

it('records the visitor\'s rating of a reply in their conversation', function (): void {
    $id = HostedChatVisitor::startConversation($this->agent);
    $reply = Message::factory()->fromAssistant()->create(['chat_conversation_id' => Conversation::query()->firstWhere('external_id', $id)->id]);

    $this->patchJson($this->url, ['conversation_id' => $id, 'message_id' => $reply->external_id, 'feedback' => 'positive'])
        ->assertOk();

    expect($reply->fresh()->feedback)->toBe(MessageFeedback::Positive);
});

it('keeps the visitor\'s note on a thumbs-down', function (): void {
    $id = HostedChatVisitor::startConversation($this->agent);
    $reply = Message::factory()->fromAssistant()->create(['chat_conversation_id' => Conversation::query()->firstWhere('external_id', $id)->id]);

    $this->patchJson($this->url, ['conversation_id' => $id, 'message_id' => $reply->external_id, 'feedback' => 'negative', 'comment' => 'The opening hours are old.'])
        ->assertOk();

    expect($reply->fresh()->feedback_comment)->toBe('The opening hours are old.');
});

it('rejects a note longer than the limit', function (): void {
    $this->patchJson($this->url, ['conversation_id' => (string) Str::uuid(), 'message_id' => (string) Str::uuid(), 'feedback' => 'negative', 'comment' => Str::repeat('a', 1001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['comment']);
});

it('does not record a rating for a reply in another visitor\'s conversation', function (): void {
    $id = HostedChatVisitor::startConversation($this->agent);
    $reply = Message::factory()->fromAssistant()->create(['chat_conversation_id' => Conversation::query()->firstWhere('external_id', $id)->id]);

    // A fresh browser session is a different visitor.
    $this->flushSession();

    $this->patchJson($this->url, ['conversation_id' => $id, 'message_id' => $reply->external_id, 'feedback' => 'negative'])->assertOk();

    expect($reply->fresh()->feedback)->toBeNull();
});
