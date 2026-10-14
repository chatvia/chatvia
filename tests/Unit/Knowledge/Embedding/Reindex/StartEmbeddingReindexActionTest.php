<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Embedding\Actions\Reindex\StartEmbeddingReindexAction;
use Domain\Knowledge\Embedding\Jobs\ReembedChunksJob;
use Domain\Knowledge\Embedding\Services\EmbeddingReindexStatus;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;

/**
 * A re-index is one job batch on the ingestion queue: the chunks and the Q&A
 * questions in id ranges of 32 rows. Its progress is read back from the
 * batch, and a newer re-index cancels one that still runs.
 */

/** @return list<int> the ids of the new chunks, in order */
function reindexChunks(SourceFile $file, int $count): array
{
    $vector = '['.implode(',', array_fill(0, 1024, 0.1)).']';

    DB::table('chunks')->insert(array_map(fn (int $index): array => [
        'external_id' => (string) Str::uuid7(),
        'workspace_id' => $file->workspace_id,
        'chunkable_type' => $file->getMorphClass(),
        'chunkable_id' => $file->id,
        'chunk_index' => $index + 1,
        'chunk' => "Page {$index}.",
        'tokens' => 2,
        'embedding' => $vector,
        'embedding_bm25' => Bm25Tokenizer::expression("Page {$index}."),
        'checksum' => md5("page {$index}"),
        'cost_in_micro_cents' => 0,
    ], range(0, $count - 1)));

    return Chunk::query()->ofSource($file)->orderBy('id')->pluck('id')->all();
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
