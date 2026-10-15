<?php

use Domain\Conversation\Models\Message;

/**
 * MessageQueryBuilder::searchContent() matches any word of the term and ranks
 * the matches by full-text rank, computed for the matched rows only.
 */
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
