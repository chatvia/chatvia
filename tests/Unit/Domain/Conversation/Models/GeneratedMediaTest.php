<?php

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\Storage;

/**
 * A generated file goes with its row, its conversation and the reply that
 * made it, whichever is deleted.
 */
beforeEach(function (): void {
    Storage::fake(GeneratedMedia::DISK);
});

it('wipes the stored file with its row, also when the reply that made it is deleted', function (): void {
    $conversation = Conversation::factory()->create();
    $deleted = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $made = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $kept = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $reply = Message::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'metadata' => ['artifacts' => [['kind' => 'image', 'data' => ['id' => $made->external_id]]]],
    ]);

    $deleted->delete();
    $reply->delete();

    expect(GeneratedMedia::query()->whereKey($made->id)->exists())->toBeFalse()
        ->and(GeneratedMedia::query()->whereKey($kept->id)->exists())->toBeTrue();
    Storage::disk(GeneratedMedia::DISK)->assertMissing([$deleted->filepath, $made->filepath]);
});

it('goes with its conversation', function (): void {
    $conversation = Conversation::factory()->create();
    $media = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();

    $conversation->delete();

    expect(GeneratedMedia::query()->whereKey($media->id)->exists())->toBeFalse();
    Storage::disk(GeneratedMedia::DISK)->assertMissing($media->filepath);
});
