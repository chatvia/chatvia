<?php

use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deleting an attachment row wipes its stored file, but only once the delete
 * commits, so a row that survives a rolled-back delete still has its file.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

function attachmentModelUpload(): Attachment
{
    $filepath = 'workspaces/w/chat/'.Str::uuid().'.pdf';
    Storage::disk(Attachment::DISK)->put($filepath, 'fake pdf');

    return Attachment::create([
        'chat_conversation_id' => Conversation::factory()->create()->id,
        'filepath' => $filepath,
        'filename' => 'job-posting.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 8,
    ]);
}

it('wipes the stored file when the row is deleted', function (): void {
    $attachment = attachmentModelUpload();

    $attachment->delete();

    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('keeps the stored file when the surrounding delete rolls back', function (): void {
    $attachment = attachmentModelUpload();

    try {
        DB::transaction(function () use ($attachment): void {
            $attachment->delete();

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});
