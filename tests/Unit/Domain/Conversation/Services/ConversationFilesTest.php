<?php

use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatStoragePath;
use Domain\Conversation\Services\ConversationFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting a conversation wipes its whole chat folder: uploads, generated
 * media and replies read aloud, which have no row of their own.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

/** A file in a conversation's chat folder. */
function cfPut(Conversation $conversation, string $relative): string
{
    $path = ChatStoragePath::forConversation($conversation->agent, $conversation->external_id, $relative);
    Storage::disk(Attachment::DISK)->put($path, 'x');

    return $path;
}

it('wipes the conversations\' folders and keeps the others', function (): void {
    $gone = Conversation::factory()->create();
    $kept = Conversation::factory()->forAgent($gone->agent)->create();
    $speech = cfPut($gone, 'speech/m1-abc.mp3');
    $image = cfPut($gone, 'images/i1.png');
    $other = cfPut($kept, 'speech/m1-abc.mp3');

    app(ConversationFiles::class)->forget([$gone]);

    Storage::disk(Attachment::DISK)->assertMissing([$speech, $image]);
    Storage::disk(Attachment::DISK)->assertExists($other);
});

it('wipes the folder of a deleted agent\'s conversation, from a bare row', function (): void {
    $conversation = Conversation::factory()->create();
    $speech = cfPut($conversation, 'speech/m1-abc.mp3');
    $conversation->agent->delete();

    app(ConversationFiles::class)->forget([(object) ['agent_id' => $conversation->agent_id, 'external_id' => $conversation->external_id]]);

    Storage::disk(Attachment::DISK)->assertMissing($speech);
});

it('waits for the commit, so a rolled-back delete keeps the files', function (): void {
    $conversation = Conversation::factory()->create();
    $speech = cfPut($conversation, 'speech/m1-abc.mp3');

    rescue(fn () => DB::transaction(function () use ($conversation): void {
        app(ConversationFiles::class)->forget([$conversation]);

        throw new RuntimeException('rolled back');
    }), report: false);

    Storage::disk(Attachment::DISK)->assertExists($speech);
});

it('runs when a conversation is deleted', function (): void {
    $conversation = Conversation::factory()->create();
    $speech = cfPut($conversation, 'speech/m1-abc.mp3');

    $conversation->delete();

    Storage::disk(Attachment::DISK)->assertMissing($speech);
});
