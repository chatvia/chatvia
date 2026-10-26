<?php

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Media\GeneratedMediaFiles;
use Illuminate\Support\Facades\Storage;

/**
 * Generated images and audio live under the agent's folder, one file per
 * row, and go with their conversation or with the reply that made them.
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

it('keeps each file under the agent, per conversation', function (): void {
    $conversation = Conversation::factory()->create();

    expect($this->files->pathFor($conversation->agent, $conversation->external_id, 'm1', 'png'))
        ->toBe($conversation->agent->settingsStorageRoot().'/generated/'.$conversation->external_id.'/m1.png');
});

it('forgets every item of the given conversations and keeps the others', function (): void {
    $gone = GeneratedMedia::factory()->stored()->create();
    $kept = GeneratedMedia::factory()->stored()->create();

    $this->files->forgetConversations([$gone->chat_conversation_id]);

    expect(GeneratedMedia::query()->find($gone->id))->toBeNull()
        ->and(GeneratedMedia::query()->find($kept->id))->not->toBeNull();
    Storage::disk(GeneratedMedia::DISK)->assertMissing($gone->filepath);
    Storage::disk(GeneratedMedia::DISK)->assertExists($kept->filepath);
});

it('forgets the items a reply points at, in its own conversation only', function (): void {
    $conversation = Conversation::factory()->create();
    $made = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $other = GeneratedMedia::factory()->forConversation($conversation)->stored()->create();
    $elsewhere = GeneratedMedia::factory()->stored()->create();

    $this->files->forgetReplies($conversation->id, [gmfArtifacts($made->external_id, $elsewhere->external_id), null]);

    expect(GeneratedMedia::query()->find($made->id))->toBeNull()
        ->and(GeneratedMedia::query()->find($other->id))->not->toBeNull()
        ->and(GeneratedMedia::query()->find($elsewhere->id))->not->toBeNull();
});

it('reads only image and audio artifacts with an id', function (): void {
    $metadata = ['artifacts' => [
        ['kind' => 'image', 'data' => ['id' => 'a']],
        ['kind' => 'audio', 'data' => ['id' => 'b']],
        ['kind' => 'form', 'data' => ['id' => 'c']],
        ['kind' => 'image', 'data' => []],
        'not an artifact',
    ]];

    expect(GeneratedMediaFiles::mediaIds($metadata))->toBe(['a', 'b'])
        ->and(GeneratedMediaFiles::mediaIds(null))->toBe([]);
});
