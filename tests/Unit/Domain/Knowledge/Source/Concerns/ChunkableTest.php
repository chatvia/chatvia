<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

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

it('deletes every chunk of a source in batches, the summary included', function (): void {
    $file = AgentFileFactory::new()->create();
    Chunk::factory()->forSource($file)->summary()->create();
    Chunk::factory()->forSource($file)->count(4)->create();
    $other = AgentFileFactory::new()->create(['workspace_id' => $file->workspace_id]);
    $kept = Chunk::factory()->forSource($other)->create();

    $deletes = 0;
    DB::listen(function (QueryExecuted $query) use (&$deletes): void {
        if (str_starts_with($query->sql, 'DELETE FROM chunks ')) {
            $deletes++;
        }
    });

    $file->deleteChunks(batchSize: 2);

    // 5 chunks in batches of 2 → 3 batches, then 1 that finds nothing.
    expect($deletes)->toBe(4)
        ->and(Chunk::query()->ofSource($file)->exists())->toBeFalse()
        ->and(Chunk::query()->find($kept->id))->not->toBeNull();
});

it('deletes all chunks of a source bigger than one batch', function (): void {
    $file = AgentFileFactory::new()->create();
    $vector = embeddingVector();

    DB::statement(
        'INSERT INTO chunks (workspace_id, chunkable_type, chunkable_id, chunk_index, chunk, tokens, embedding, search_text, checksum)
         SELECT ?, ?, ?, g, ?, 1, ?::'.app(EmbeddingStorage::class)->columnType().', ?, md5(g::text) FROM generate_series(0, 2500) g',
        [$file->workspace_id, $file->getMorphClass(), $file->id, 'Text.', $vector, 'Text.'],
    );

    $file->deleteChunks();

    expect(Chunk::query()->ofSource($file)->exists())->toBeFalse();
});
