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

it('wipes the stored file when its row is deleted', function (): void {
    $media = GeneratedMedia::factory()->stored()->create();

    $media->delete();

    Storage::disk(GeneratedMedia::DISK)->assertMissing($media->filepath);
});

it('deletes a pending row that has no file yet', function (): void {
    $media = GeneratedMedia::factory()->create();

    $media->delete();

    expect(GeneratedMedia::query()->find($media->id))->toBeNull();
});

it('goes with its conversation', function (): void {
    $conversation = Conversation::factory()->create();
    $media = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();

    $conversation->delete();

    expect(GeneratedMedia::query()->find($media->id))->toBeNull();
    Storage::disk(GeneratedMedia::DISK)->assertMissing($media->filepath);
});

it('goes with the reply that made it', function (): void {
    $conversation = Conversation::factory()->create();
    $media = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $kept = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $reply = Message::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'metadata' => ['artifacts' => [['kind' => 'image', 'data' => ['id' => $media->external_id]]]],
    ]);

    $reply->delete();

    expect(GeneratedMedia::query()->find($media->id))->toBeNull()
        ->and(GeneratedMedia::query()->find($kept->id))->not->toBeNull();
});

it('opens through a signed link without the host', function (): void {
    $media = GeneratedMedia::factory()->stored()->create();

    expect($media->url)->toContain('/generated-media/'.$media->external_id)
        ->and($media->url)->toContain('signature=');
});
