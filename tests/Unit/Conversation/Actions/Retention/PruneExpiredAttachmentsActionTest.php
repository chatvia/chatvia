<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Retention\PruneExpiredAttachmentsAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The attachment sweep removes uploads that never joined a conversation; a
 * conversation's files go with the conversation (see
 * PruneRetentionActionsTest). The row and its stored file are deleted
 * together, so no row ever points at a deleted file.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

function prunedConversation(array $attributes = []): Conversation
{
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::Widget,
        ...$attributes,
    ]);
}

function storedUpload(?Conversation $conversation, ?int $daysOld = null): Attachment
{
    $path = 'users/x/agents/y/attachments/'.Str::uuid().'.pdf';
    Storage::disk(Attachment::DISK)->put($path, 'pdf');

    $attachment = Attachment::create([
        'chat_conversation_id' => $conversation?->id,
        'filepath' => $path,
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 3,
    ]);

    if ($daysOld !== null) {
        DB::table('chat_attachments')->where('id', $attachment->id)->update(['created_at' => now()->subDays($daysOld)]);
    }

    return $attachment;
}

it('never touches the files of a conversation, live or expired, however old they are', function (): void {
    // An expired conversation's files go with the conversation prune.
    $live = storedUpload(prunedConversation(['expires_at' => now()->addDays(10)]), daysOld: 400);
    $expired = storedUpload(prunedConversation(['expires_at' => now()->subDay()]), daysOld: 400);

    expect(app(PruneExpiredAttachmentsAction::class)->execute(orphanDays: 30, chunk: 100))->toBe(0)
        ->and(Attachment::query()->whereKey([$live->id, $expired->id])->count())->toBe(2);
    Storage::disk(Attachment::DISK)->assertExists([$live->filepath, $expired->filepath]);
});

it('deletes an upload that never joined a conversation only after the orphan window', function (): void {
    $fresh = storedUpload(null, daysOld: 2);
    $stale = storedUpload(null, daysOld: 31);

    expect(app(PruneExpiredAttachmentsAction::class)->execute(orphanDays: 30, chunk: 100))->toBe(1)
        ->and(Attachment::query()->find($fresh->id))->not->toBeNull()
        ->and(Attachment::query()->find($stale->id))->toBeNull();
});

it('deletes stale uploads in chunks', function (): void {
    $stale = collect(range(1, 3))->map(fn (): Attachment => storedUpload(null, daysOld: 31));

    expect(app(PruneExpiredAttachmentsAction::class)->execute(orphanDays: 30, chunk: 1))->toBeGreaterThanOrEqual(3)
        ->and(Attachment::query()->whereKey($stale->pluck('id'))->exists())->toBeFalse();
    $stale->each(fn (Attachment $upload) => Storage::disk(Attachment::DISK)->assertMissing($upload->filepath));
});

it('runs from the scheduled command', function (): void {
    $upload = storedUpload(null, daysOld: 31);

    $this->artisan('app:prune-chat-attachments')->assertSuccessful();

    expect(Attachment::query()->find($upload->id))->toBeNull();
});
