<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;

/**
 * forAgentConversation() promises "this agent's conversation", so it must
 * scope by the agent itself — a caller that skipped its own ownership check
 * still must not read another agent's messages.
 */
function mqbConversation(Agent $agent): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
    ]);
}

it('returns the messages of the agent own conversation', function (): void {
    $agent = Agent::factory()->create();
    $conversation = mqbConversation($agent);
    $message = Message::factory()->create(['chat_conversation_id' => $conversation->id]);

    $ids = Message::forAgentConversation($agent->id, $conversation->id)->pluck('id')->all();

    expect($ids)->toBe([$message->id]);
});

it('returns nothing for a conversation that belongs to another agent', function (): void {
    $agent = Agent::factory()->create();
    $otherAgent = Agent::factory()->create();
    $foreign = mqbConversation($otherAgent);
    Message::factory()->create(['chat_conversation_id' => $foreign->id]);

    expect(Message::forAgentConversation($agent->id, $foreign->id)->exists())->toBeFalse();
});
