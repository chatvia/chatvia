<?php

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Media\GeneratedMediaFiles;
use Illuminate\Support\Facades\Storage;

/**
 * Generated images and audio go with their conversation or with the reply
 * that made them, and a reply only takes items of its own conversation.
 */
beforeEach(function (): void {
    Storage::fake(GeneratedMedia::DISK);
    $this->files = app(GeneratedMediaFiles::class);
});

/** @return array<string, mixed> */
function gmfArtifacts(string ...$ids): array
{
    return ['artifacts' => array_map(static fn (string $id): array => ['kind' => 'image', 'data' => ['id' => $id, 'status' => 'pending']], $ids)];
}

it('forgets every item of the given conversations and keeps the others', function (): void {
    $gone = GeneratedMedia::factory()->stored()->create();
    $kept = GeneratedMedia::factory()->stored()->create();

    $this->files->forgetConversations([$gone->chat_conversation_id]);

    expect(GeneratedMedia::query()->whereKey($gone->id)->exists())->toBeFalse()
        ->and(GeneratedMedia::query()->whereKey($kept->id)->exists())->toBeTrue();
    Storage::disk(GeneratedMedia::DISK)->assertMissing($gone->filepath);
    Storage::disk(GeneratedMedia::DISK)->assertExists($kept->filepath);
});

it('forgets the items a reply points at, in its own conversation only', function (): void {
    $conversation = Conversation::factory()->create();
    $made = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $other = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $elsewhere = GeneratedMedia::factory()->stored()->create();

    $this->files->forgetReplies($conversation->id, [gmfArtifacts($made->external_id, $elsewhere->external_id), null]);

    expect(GeneratedMedia::query()->whereKey($made->id)->exists())->toBeFalse()
        ->and(GeneratedMedia::query()->whereKey([$other->id, $elsewhere->id])->count())->toBe(2);
});
