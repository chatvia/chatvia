<?php

use Domain\Agent\Models\Agent;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The visitor removes a message from a conversation of their own chat
 * session; a request for another visitor's conversation removes nothing.
 */
it('does not remove a message of another visitor\'s conversation', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $id = HostedChatVisitor::startConversation($agent, 'Where is my order?');
    $memory = HostedChatVisitor::memory($agent, $id);
    $firstMessageId = $memory->getMessagesForConversation()[0]->id;

    // A fresh browser session is a different visitor.
    $this->flushSession();

    $this->deleteJson("/chat/{$agent->slug}/messages", ['conversation_id' => $id, 'message_id' => $firstMessageId])->assertOk();

    expect(array_column($memory->getMessagesForConversation(), 'content'))->toBe(['Where is my order?']);
});
