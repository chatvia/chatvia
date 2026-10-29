<?php

use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting an attachment row wipes its stored file, but only once the delete
 * commits, so a row that survives a rolled-back delete still has its file.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

it('wipes the stored file when the row is deleted', function (): void {
    $attachment = Attachment::factory()->stored()->create();

    $attachment->delete();

    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('keeps the stored file when the surrounding delete rolls back', function (): void {
    $attachment = Attachment::factory()->stored()->create();

    try {
        DB::transaction(function () use ($attachment): void {
            $attachment->delete();

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(Attachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});
