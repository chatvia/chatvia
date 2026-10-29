<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Locks the contract Files.vue relies on: 100% means "done" and nothing else. A
 * file that is still Processing must never report 100 even once every expected
 * chunk has been imported — otherwise the UI renders "ingested" and then reverts
 * to "Training in progress" on the next read that still sees Processing (the
 * batch `finally()` not yet committed). Only the Completed short-circuit yields 100.
 * Deleting a source's chunks goes in batches, so a big source never holds
 * one long statement.
 */
it('reports progress from the imported chunks, 100 only once the file is completed', function (string $status, ?int $expected, ?int $imported, int $progress): void {
    $file = new SourceFile;
    $file->status = $status;
    $file->expected_chunks = $expected;
    $file->chunks_count = $imported;

    expect($file->ingestionProgress())->toBe($progress);
})->with([
    'processing, every chunk imported: capped at 99' => ['ingesting', 570, 570, 99],
    'processing, part imported' => ['ingesting', 100, 45, 45],
    'completed' => ['ingested', null, null, 100],
    'not started' => ['pending', null, null, 0],
]);

it('deletes every chunk of a source in batches, the summary included', function (): void {
    $file = AgentFileFactory::new()->create();
    Chunk::factory()->forSource($file)->summary()->create();
    Chunk::factory()->forSource($file)->count(4)->create();
    $other = AgentFileFactory::new()->create(['workspace_id' => $file->workspace_id]);
    $kept = Chunk::factory()->forSource($other)->create();

    $deletes = 0;
    DB::listen(function (QueryExecuted $query) use (&$deletes): void {
        if (Str::startsWith($query->sql, 'DELETE FROM chunks ')) {
            $deletes++;
        }
    });

    $file->deleteChunks(batchSize: 2);

    expect($deletes)->toBe(4)
        ->and(Chunk::query()->ofSource($file)->exists())->toBeFalse()
        ->and(Chunk::query()->find($kept->id))->not->toBeNull();
});
