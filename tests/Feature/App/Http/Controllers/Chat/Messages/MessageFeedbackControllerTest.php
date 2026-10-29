<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The visitor rates a reply in a conversation of their own chat session; a
 * rating sent for another visitor's conversation changes nothing. A note on
 * a thumbs-down is kept only while the agent asks for one.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->url = "/chat/{$this->agent->slug}/messages/feedback";
});

it('records the visitor\'s rating, keeping a note on a thumbs-down only while the agent asks for one', function (): void {
    $id = HostedChatVisitor::startConversation($this->agent);
    $conversationId = Conversation::query()->firstWhere('external_id', $id)->id;
    $first = Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversationId]);
    $second = Message::factory()->fromAssistant()->create(['chat_conversation_id' => $conversationId]);
    $rate = fn (Message $reply) => $this->patchJson($this->url, ['conversation_id' => $id, 'message_id' => $reply->external_id, 'feedback' => 'negative', 'comment' => 'The opening hours are old.'])
        ->assertOk();

    $rate($first);
    $this->agent->setSetting('allow_feedback_comment', false, 'appearance');
    $rate($second);

    expect($first->fresh()->feedback)->toBe(MessageFeedback::Negative)
        ->and($first->fresh()->feedback_comment)->toBe('The opening hours are old.')
        ->and($second->fresh()->feedback)->toBe(MessageFeedback::Negative)
        ->and($second->fresh()->feedback_comment)->toBeNull();
});

it('does not record a rating for a reply in another visitor\'s conversation', function (): void {
    $id = HostedChatVisitor::startConversation($this->agent);
    $reply = Message::factory()->fromAssistant()->create(['chat_conversation_id' => Conversation::query()->firstWhere('external_id', $id)->id]);

    $this->flushSession();

    $this->patchJson($this->url, ['conversation_id' => $id, 'message_id' => $reply->external_id, 'feedback' => 'negative'])->assertOk();

    expect($reply->fresh()->feedback)->toBeNull();
});
