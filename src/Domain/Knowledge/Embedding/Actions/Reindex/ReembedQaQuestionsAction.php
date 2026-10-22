<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Actions\Reindex;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Embeds the Q&A questions in one id range again with the embedding model in
 * use, so a visitor's message still matches them by meaning. One part of a
 * re-index ({@see StartEmbeddingReindexAction}). The new vectors are written
 * with one UPDATE for the whole range.
 */
class ReembedQaQuestionsAction
{
    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
        private readonly EmbeddingStorage $storage,
    ) {}

    /**
     * @return int how many questions were embedded again
     */
    public function execute(int $firstId, int $lastId): int
    {
        $questions = AgentQuestionAnswerQuestion::query()
            ->whereBetween('id', [$firstId, $lastId])
            ->orderBy('id')
            ->get(['id', 'question'])
            ->values();

        if ($questions->isEmpty()) {
            return 0;
        }

        $vectors = $this->embeddings->embedBatch($questions->pluck('question')->all());

        // Cast to the column's type (the install's choice).
        $cast = $this->storage->columnType();
        $rows = [];
        $bindings = [];

        foreach ($questions as $position => $question) {
            $rows[] = "(?::bigint, ?::{$cast})";
            $bindings[] = $question->id;
            $bindings[] = '['.implode(',', $vectors[$position]).']';
        }

        $table = $questions->first()->getTable();

        DB::update(
            "UPDATE {$table} AS t SET embedding = v.embedding
             FROM (VALUES ".implode(', ', $rows).') AS v(id, embedding)
             WHERE t.id = v.id',
            $bindings,
        );

        return $questions->count();
    }
}
