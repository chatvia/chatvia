<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;

/**
 * forAgentConversation() promises "this agent's conversation", so it must
 * scope by the agent itself: a caller that skipped its own ownership check
 * still must not read another agent's messages. searchContent() matches any
 * word of the term and ranks the matches by full-text rank.
 */
it('returns the messages of the agent own conversation', function (): void {
    $agent = Agent::factory()->create();
    $conversation = Conversation::factory()->forAgent($agent)->create();
    $message = Message::factory()->create(['chat_conversation_id' => $conversation->id]);

    $ids = Message::forAgentConversation($agent->id, $conversation->id)->pluck('id')->all();

    expect($ids)->toBe([$message->id]);
});

it('returns nothing for a conversation that belongs to another agent', function (): void {
    $agent = Agent::factory()->create();
    $otherAgent = Agent::factory()->create();
    $foreign = Conversation::factory()->forAgent($otherAgent)->create();
    Message::factory()->create(['chat_conversation_id' => $foreign->id]);

    expect(Message::forAgentConversation($agent->id, $foreign->id)->exists())->toBeFalse();
});

it('finds a message by content and excludes non-matches', function (): void {
    $needle = Message::factory()->create(['content' => 'The warranty covers accidental water damage for two years.']);
    $other = Message::factory()->create(['content' => 'Our office is open from nine in the morning until five.']);

    $matches = Message::query()
        ->searchContent('warranty water damage')
        ->whereIn('id', [$needle->id, $other->id])
        ->get();

    expect($matches->pluck('id')->all())->toBe([$needle->id]);
});

it('ranks the message sharing more words first', function (): void {
    $weak = Message::factory()->create(['content' => 'Is there a warranty?']);
    $strong = Message::factory()->create(['content' => 'The warranty covers water damage.']);

    $matches = Message::query()
        ->searchContent('warranty water damage')
        ->whereIn('id', [$weak->id, $strong->id])
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

it('returns nothing when no message content matches', function (): void {
    $message = Message::factory()->create(['content' => 'The warranty covers accidental water damage.']);

    $matches = Message::query()
        ->searchContent('xylophone helicopter quarterly')
        ->whereIn('id', [$message->id])
        ->get();

    expect($matches)->toBeEmpty();
});
