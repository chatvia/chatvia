<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Embedding\Actions\Reindex\StartEmbeddingReindexAction;
use Domain\Knowledge\Embedding\Jobs\ReembedChunksJob;
use Domain\Knowledge\Embedding\Services\EmbeddingReindexStatus;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;

/**
 * A re-index is one job batch on the ingestion queue: the chunks and the Q&A
 * questions in id ranges of 32 rows. Its progress is read back from the
 * batch, and a newer re-index cancels one that still runs.
 */

/** @return list<int> the ids of the new chunks, in order */
function reindexChunks(SourceFile $file, int $count): array
{
    return Chunk::factory()->forSource($file)->count($count)
        ->sequence(fn (Sequence $sequence): array => ['chunk_index' => $sequence->index + 1])
        ->create()
        ->modelKeys();
}

it('queues the chunks in ranges of 32 rows, on the ingestion queue', function (): void {
    Bus::fake();
    Chunk::query()->delete();
    $ids = reindexChunks(AgentFileFactory::new()->create(), 33);

    $batch = app(StartEmbeddingReindexAction::class)->execute();

    expect($batch)->toBeInstanceOf(Batch::class)
        ->and(app(EmbeddingReindexStatus::class)->running()?->id)->toBe($batch->id);

    Bus::assertBatched(function (PendingBatchFake $pending) use ($ids): bool {
        $chunkJobs = $pending->jobs->whereInstanceOf(ReembedChunksJob::class)->values();

        return $pending->name === 'Embedding re-index'
            && $pending->queue() === 'ingestion.chunks'
            && $pending->allowsFailures()
            && $chunkJobs->count() === 2
            && [$chunkJobs[0]->firstId, $chunkJobs[0]->lastId] === [$ids[0], $ids[31]]
            && [$chunkJobs[1]->firstId, $chunkJobs[1]->lastId] === [$ids[32], $ids[32]];
    });
});

it('starts nothing while nothing is stored as vectors', function (): void {
    Bus::fake();
    Chunk::query()->delete();
    DB::table('agent_question_answer_questions')->delete();

    expect(app(StartEmbeddingReindexAction::class)->execute())->toBeNull()
        ->and(app(EmbeddingReindexStatus::class)->latest())->toBeNull();

    Bus::assertNothingBatched();
});

it('cancels a re-index that still runs before it starts a new one', function (): void {
    Bus::fake();
    reindexChunks(AgentFileFactory::new()->create(), 2);

    $first = app(StartEmbeddingReindexAction::class)->execute();
    $second = app(StartEmbeddingReindexAction::class)->execute();

    expect(Bus::findBatch($first->id)->cancelled())->toBeTrue()
        ->and(app(EmbeddingReindexStatus::class)->running()?->id)->toBe($second->id);
});
