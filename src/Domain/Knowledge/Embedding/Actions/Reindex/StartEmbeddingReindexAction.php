<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Actions\Reindex;

use Domain\Knowledge\Embedding\Jobs\ReembedChunksJob;
use Domain\Knowledge\Embedding\Jobs\ReembedQaQuestionsJob;
use Domain\Knowledge\Embedding\Services\EmbeddingReindexStatus;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Embeds every stored chunk and Q&A question again with the embedding model
 * in use. After a change of the embedding model the stored vectors are the
 * old model's, and vectors of two models cannot be compared, so search would
 * miss them. Only the vectors are made again, from the text the rows already
 * hold: no file is read again, and the only model calls are the embeddings.
 * The HNSW indexes follow each row update; nothing is retrained.
 *
 * The work is one job batch on the ingestion queue, a few rows per job, so it
 * runs beside ingestion and takes long on a large install. A re-index that
 * still runs is cancelled first: a newer one replaces it.
 */
class StartEmbeddingReindexAction
{
    /** Rows per job: as many as a plain ingestion job embeds at once. */
    public const int ROWS_PER_JOB = 32;

    public function __construct(
        private readonly EmbeddingReindexStatus $status,
    ) {}

    /**
     * @return Batch|null null when nothing is stored as vectors yet
     */
    public function execute(): ?Batch
    {
        $this->status->running()?->cancel();

        $jobs = [
            ...$this->parts('chunks')->map(fn (object $part): ReembedChunksJob => new ReembedChunksJob((int) $part->first_id, (int) $part->last_id))->all(),
            ...$this->parts('agent_question_answer_questions')->map(fn (object $part): ReembedQaQuestionsJob => new ReembedQaQuestionsJob((int) $part->first_id, (int) $part->last_id))->all(),
        ];

        if ($jobs === []) {
            $this->status->forget();

            return null;
        }

        $batch = Bus::batch($jobs)
            ->name('Embedding re-index')
            // A batch puts every job on its own queue, not on the job's.
            ->onQueue('ingestion.chunks')
            ->allowFailures()
            ->dispatch();

        $this->status->remember($batch);

        return $batch;
    }

    /**
     * The table's rows in id ranges of {@see ROWS_PER_JOB} rows each, read in
     * one query, so a large table never loads all its ids.
     *
     * @return Collection<int, object{first_id: int, last_id: int}>
     */
    private function parts(string $table): Collection
    {
        $numbered = DB::table($table)->selectRaw('id, (row_number() OVER (ORDER BY id) - 1) / ? AS part', [self::ROWS_PER_JOB]);

        /** @var Collection<int, object{first_id: int, last_id: int}> */
        return DB::query()
            ->fromSub($numbered, 'numbered')
            ->selectRaw('min(id) AS first_id, max(id) AS last_id')
            ->groupBy('part')
            ->orderBy('first_id')
            ->get();
    }
}
