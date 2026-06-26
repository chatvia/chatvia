<?php

use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\DB;

/**
 * Message content is stored as plaintext (no longer encrypted) and indexed for
 * keyword search via the `content_bm25` column, which Message::booted() keeps
 * in sync with `content`. MessageQueryBuilder::searchContent() ranks by BM25.
 *
 * REQUIRES the migrated + seeded test DB (the vchord_bm25 / tokenizer
 * extensions and the MessageFactory conversation graph).
 */
it('stores message content as plaintext and populates the bm25 vector', function (): void {
    $plaintext = 'My email is jane@example.com and my order is #4821.';

    $message = Message::factory()->create(['content' => $plaintext]);

    // Stored verbatim — no ciphertext.
    expect(DB::table('chat_messages')->where('id', $message->id)->value('content'))->toBe($plaintext);

    // Searchable vector derived alongside it.
    expect(DB::table('chat_messages')->where('id', $message->id)->value('content_bm25'))->not->toBeNull();
});

it('finds a message by content via bm25 search and excludes non-matches', function (): void {
    $needle = Message::factory()->create(['content' => 'The warranty covers accidental water damage for two years.']);
    $other = Message::factory()->create(['content' => 'Our office is open from nine in the morning until five.']);

    $matches = Message::query()
        ->searchContent('warranty water damage')
        ->whereIn('id', [$needle->id, $other->id])
        ->get();

    // Only the message that actually shares query terms comes back, ranked first.
    expect($matches->pluck('id')->all())->toBe([$needle->id]);
});

it('returns nothing when no message content matches', function (): void {
    $message = Message::factory()->create(['content' => 'The warranty covers accidental water damage.']);

    $matches = Message::query()
        ->searchContent('xylophone helicopter quarterly')
        ->whereIn('id', [$message->id])
        ->get();

    expect($matches)->toBeEmpty();
});

it('round-trips null content without a bm25 vector', function (): void {
    $message = Message::factory()->create(['content' => null]);

    expect(DB::table('chat_messages')->where('id', $message->id)->value('content'))->toBeNull()
        ->and(DB::table('chat_messages')->where('id', $message->id)->value('content_bm25'))->toBeNull();
});
