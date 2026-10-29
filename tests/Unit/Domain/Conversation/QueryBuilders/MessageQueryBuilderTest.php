<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;

/**
 * forAgentConversation() promises "this agent's conversation", so it must
 * scope by the agent itself: a caller that skipped its own ownership check
 * still must not read another agent's messages. searchContent() matches any
 * word of the term, ranks the matches by full-text rank and reads quotes and
 * operators in the term as plain text.
 */
it('returns the messages of the agent\'s own conversation and none of another agent\'s', function (): void {
    $agent = Agent::factory()->create();
    $conversation = Conversation::factory()->forAgent($agent)->create();
    $message = Message::factory()->create(['chat_conversation_id' => $conversation->id]);
    $foreign = Conversation::factory()->forAgent(Agent::factory()->create())->create();
    Message::factory()->create(['chat_conversation_id' => $foreign->id]);

    expect(Message::forAgentConversation($agent->id, $conversation->id)->pluck('id')->all())->toBe([$message->id])
        ->and(Message::forAgentConversation($agent->id, $foreign->id)->exists())->toBeFalse();
});

it('finds matching messages, the one sharing more words first, and leaves out the rest', function (): void {
    $weak = Message::factory()->create(['content' => 'Is there a warranty?']);
    $strong = Message::factory()->create(['content' => 'The warranty covers water damage.']);
    $other = Message::factory()->create(['content' => 'Our office is open from nine in the morning until five.']);

    $matches = Message::query()
        ->searchContent('warranty water damage')
        ->whereIn('id', [$weak->id, $strong->id, $other->id])
        ->get();

    expect($matches->pluck('id')->all())->toBe([$strong->id, $weak->id]);
});

it('treats quotes and operators in the term as plain text', function (): void {
    $message = Message::factory()->create(['content' => "The customer's order shipped."]);

    $matches = Message::query()
        ->searchContent("customer's & order |")
        ->whereIn('id', [$message->id])
        ->get();

    expect($matches->pluck('id')->all())->toBe([$message->id]);
});
