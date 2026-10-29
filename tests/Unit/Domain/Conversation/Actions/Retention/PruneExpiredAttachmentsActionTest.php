<?php

use Domain\Conversation\Actions\Retention\PruneExpiredAttachmentsAction;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Storage;

/**
 * The attachment sweep removes uploads that never joined a conversation; a
 * conversation's files go with the conversation (see
 * PruneExpiredConversationsActionTest). The row and its stored file are
 * deleted together, so no row ever points at a deleted file.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

it('never touches the files of a conversation, live or expired, however old they are', function (): void {
    $live = Attachment::factory()->forConversation(Conversation::factory()->expiresAt(now()->addDays(10))->create())->stored()->create(['created_at' => now()->subDays(400)]);
    $expired = Attachment::factory()->forConversation(Conversation::factory()->expiresAt(now()->subDay())->create())->stored()->create(['created_at' => now()->subDays(400)]);

    expect(app(PruneExpiredAttachmentsAction::class)->execute(orphanDays: 30, chunk: 100))->toBe(0)
        ->and(Attachment::query()->whereKey([$live->id, $expired->id])->count())->toBe(2);
    Storage::disk(Attachment::DISK)->assertExists([$live->filepath, $expired->filepath]);
});

it('deletes an upload that never joined a conversation only after the orphan window', function (): void {
    $fresh = Attachment::factory()->pending()->stored()->create(['created_at' => now()->subDays(2)]);
    $stale = Attachment::factory()->pending()->stored()->create(['created_at' => now()->subDays(31)]);

    expect(app(PruneExpiredAttachmentsAction::class)->execute(orphanDays: 30, chunk: 100))->toBe(1)
        ->and(Attachment::query()->whereKey($fresh->id)->exists())->toBeTrue()
        ->and(Attachment::query()->whereKey($stale->id)->exists())->toBeFalse();
    Storage::disk(Attachment::DISK)->assertMissing($stale->filepath);
});
