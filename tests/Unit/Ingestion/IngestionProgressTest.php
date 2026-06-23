<?php

use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Locks the contract Files.vue relies on: 100% means "done" and nothing else. A
 * file that is still Processing must never report 100 even once every expected
 * chunk has been imported — otherwise the UI renders "ingested" and then reverts
 * to "Training in progress" on the next read that still sees Processing (the
 * batch `finally()` not yet committed). Only the Completed short-circuit yields 100.
 */
it('caps progress at 99 while still processing, even when all chunks are imported', function (): void {
    $file = new SourceFile;
    $file->status = 'ingesting';
    $file->expected_chunks = 570;
    $file->chunks_count = 570;

    expect($file->ingestionProgress())->toBe(99);
});

it('reports partial progress while processing', function (): void {
    $file = new SourceFile;
    $file->status = 'ingesting';
    $file->expected_chunks = 100;
    $file->chunks_count = 45;

    expect($file->ingestionProgress())->toBe(45);
});

it('returns 100 only once the file is marked completed', function (): void {
    $file = new SourceFile;
    $file->status = 'ingested';

    expect($file->ingestionProgress())->toBe(100);
});

it('returns 0 before processing starts', function (): void {
    $file = new SourceFile;
    $file->status = 'pending';

    expect($file->ingestionProgress())->toBe(0);
});
