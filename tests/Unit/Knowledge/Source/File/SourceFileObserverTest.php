<?php

use Database\Factories\AgentFileFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A deleted file source loses its stored objects (the upload and its parser
 * output), but only once the delete is committed: a delete that rolls back
 * must keep the files its row points to.
 */
beforeEach(function (): void {
    Storage::fake();
});

it('deletes the stored file when the row delete commits', function (): void {
    Storage::put('users/u/files/a.pdf', '%PDF-1.7');
    $file = AgentFileFactory::new()->create(['filepath' => 'users/u/files/a.pdf']);

    DB::transaction(function () use ($file): void {
        $file->delete();

        Storage::assertExists('users/u/files/a.pdf');
    });

    Storage::assertMissing('users/u/files/a.pdf');
});

it('deletes the stored file at once outside a transaction', function (): void {
    Storage::put('users/u/files/b.pdf', '%PDF-1.7');
    $file = AgentFileFactory::new()->create(['filepath' => 'users/u/files/b.pdf']);

    $file->delete();

    Storage::assertMissing('users/u/files/b.pdf');
});

it('keeps the stored file when the row delete rolls back', function (): void {
    Storage::put('users/u/files/c.pdf', '%PDF-1.7');
    $file = AgentFileFactory::new()->create(['filepath' => 'users/u/files/c.pdf']);

    try {
        DB::transaction(function () use ($file): void {
            $file->delete();

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    Storage::assertExists('users/u/files/c.pdf');
});

it('deletes the parser output with the file', function (): void {
    Storage::put('users/u/files/d.pdf', '%PDF-1.7');
    Storage::put('users/u/agents/a/d1.json', '[]');
    $file = AgentFileFactory::new()->create(['filepath' => 'users/u/files/d.pdf', 'parser_filepath' => 'users/u/agents/a/d1.json']);

    $file->delete();

    Storage::assertMissing('users/u/files/d.pdf');
    Storage::assertMissing('users/u/agents/a/d1.json');
});
