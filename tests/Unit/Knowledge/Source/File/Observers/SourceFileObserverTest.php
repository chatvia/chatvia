<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A deleted file source loses its storage folder (the upload, its parser
 * output and its page images), but only once the delete is committed: a
 * delete that rolls back must keep the files its row points to.
 */
beforeEach(function (): void {
    Storage::fake();
});

it('deletes the stored file when the row delete commits', function (): void {
    $file = AgentFileFactory::new()->create();
    Storage::put($file->filepath, '%PDF-1.7');

    DB::transaction(function () use ($file): void {
        $file->delete();

        Storage::assertExists($file->filepath);
    });

    Storage::assertMissing($file->filepath);
});

it('deletes the stored file at once outside a transaction', function (): void {
    $file = AgentFileFactory::new()->create();
    Storage::put($file->filepath, '%PDF-1.7');

    $file->delete();

    Storage::assertMissing($file->filepath);
});

it('keeps the stored file when the row delete rolls back', function (): void {
    $file = AgentFileFactory::new()->create();
    Storage::put($file->filepath, '%PDF-1.7');

    try {
        DB::transaction(function () use ($file): void {
            $file->delete();

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    Storage::assertExists($file->filepath);
});

it('deletes the whole source folder: upload, parser output and page images', function (): void {
    $file = AgentFileFactory::new()->create();
    $folder = KnowledgeStoragePath::forSource($file);
    $file->update(['parser_filepath' => $folder.'/pages.json']);

    Storage::put($file->filepath, '%PDF-1.7');
    Storage::put($file->parser_filepath, '[]');
    Storage::put($folder.'/images/page-1.jpg', 'jpeg');

    $other = AgentFileFactory::new()->create(['workspace_id' => $file->workspace_id]);
    Storage::put($other->filepath, '%PDF-1.7');

    $file->delete();

    Storage::assertMissing([$file->filepath, $file->parser_filepath, $folder.'/images/page-1.jpg']);
    Storage::assertExists($other->filepath);
});
