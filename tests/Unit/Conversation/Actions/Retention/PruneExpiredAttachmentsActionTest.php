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
 * A chat attachment lives as long as its conversation. The sweep deletes the
 * row and its stored file together, so no row ever points at a deleted file.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
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

it('keeps the files of a live conversation however old they are', function (): void {
    $upload = storedUpload(prunedConversation(['expires_at' => now()->addDays(10)]), daysOld: 400);

    expect(app(PruneExpiredAttachmentsAction::class)->execute(orphanDays: 30, chunk: 100))->toBe(0)
        ->and(Attachment::query()->find($upload->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($upload->filepath);
});

it('deletes the row and the file of a conversation past its retention', function (): void {
    $upload = storedUpload(prunedConversation(['expires_at' => now()->subDay()]));

    expect(app(PruneExpiredAttachmentsAction::class)->execute(orphanDays: 30, chunk: 100))->toBe(1)
        ->and(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($upload->filepath);
});

it('deletes the files of an already deleted conversation', function (): void {
    $conversation = prunedConversation();
    $upload = storedUpload($conversation);
    // A bulk soft-delete skips the model hook that would have removed it.
    Conversation::query()->whereKey($conversation->id)->update(['deleted_at' => now()]);

    app(PruneExpiredAttachmentsAction::class)->execute(orphanDays: 30, chunk: 100);

    expect(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($upload->filepath);
});

it('deletes an upload that never joined a conversation only after the orphan window', function (): void {
    $fresh = storedUpload(null, daysOld: 2);
    $stale = storedUpload(null, daysOld: 31);

    expect(app(PruneExpiredAttachmentsAction::class)->execute(orphanDays: 30, chunk: 100))->toBe(1)
        ->and(Attachment::query()->find($fresh->id))->not->toBeNull()
        ->and(Attachment::query()->find($stale->id))->toBeNull();
});

it('runs from the scheduled command', function (): void {
    $upload = storedUpload(prunedConversation(['expires_at' => now()->subDay()]));

    $this->artisan('app:prune-chat-attachments')->assertSuccessful();

    expect(Attachment::query()->find($upload->id))->toBeNull();
});
