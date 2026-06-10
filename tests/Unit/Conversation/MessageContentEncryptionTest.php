<?php

use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Guards the `encrypted` cast on chat_messages.content: visitor conversation
 * text must be stored as ciphertext at rest (DB + backups) and only ever
 * decrypted through the Eloquent model.
 *
 * REQUIRES the migrated + seeded test DB (MessageFactory creates a Conversation
 * graph that reuses the seeded demo data).
 */
it('stores message content as ciphertext at rest, decrypted only via the model', function (): void {
    $plaintext = 'My email is jane@example.com and my order is #4821.';

    $message = Message::factory()->create(['content' => $plaintext]);

    // Raw column (bypassing the cast) is ciphertext, not the plaintext.
    $raw = DB::table('chat_messages')->where('id', $message->id)->value('content');

    expect($raw)->not->toBe($plaintext)
        ->and(Crypt::decryptString($raw))->toBe($plaintext);

    // Re-reading through Eloquent transparently decrypts.
    expect(Message::query()->find($message->id)->content)->toBe($plaintext);
});

it('round-trips a null content without encrypting', function (): void {
    $message = Message::factory()->create(['content' => null]);

    expect(DB::table('chat_messages')->where('id', $message->id)->value('content'))->toBeNull()
        ->and(Message::query()->find($message->id)->content)->toBeNull();
});
