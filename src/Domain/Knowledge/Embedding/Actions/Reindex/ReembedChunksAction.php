<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Actions\Reindex;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Embeds the chunks in one id range again with the embedding model in use:
 * the chunk from the same text ingestion embedded (its stored `search_text`),
 * and its questions when it has them. One part of a re-index
 * ({@see StartEmbeddingReindexAction}). The text columns stay as they are.
 * The new vectors are written with one UPDATE for the whole range.
 */
class ReembedChunksAction
{
    public function __construct(
        private readonly EmbeddingsProvider $embeddings,
        private readonly EmbeddingStorage $storage,
    ) {}

    /**
     * @return int how many chunks were embedded again
     */
    public function execute(int $firstId, int $lastId): int
    {
        $chunks = Chunk::query()
            ->whereBetween('id', [$firstId, $lastId])
            ->orderBy('id')
            ->get(['id', 'search_text', 'questions'])
            ->values();

        if ($chunks->isEmpty()) {
            return 0;
        }

        $vectors = $this->embeddings->embedBatch($chunks->map(fn (Chunk $chunk): string => $chunk->search_text)->all());
        $questionVectors = $this->questionVectors($chunks->all());

        // Each value is cast to the column's type (the install's choice), so
        // the VALUES list has the right column types, NULL included.
        $cast = $this->storage->columnType();
        $rows = [];
        $bindings = [];

        foreach ($chunks as $position => $chunk) {
            $rows[] = "(?::bigint, ?::{$cast}, ?::{$cast})";
            $bindings[] = $chunk->id;
            $bindings[] = $this->literal($vectors[$position]);
            $bindings[] = isset($questionVectors[$chunk->id]) ? $this->literal($questionVectors[$chunk->id]) : null;
        }

        $table = $chunks->first()->getTable();

        DB::update(
            "UPDATE {$table} AS t SET embedding = v.embedding, questions_embedding = v.questions_embedding
             FROM (VALUES ".Arr::join($rows, ', ').') AS v(id, embedding, questions_embedding)
             WHERE t.id = v.id',
            $bindings,
        );

        return $chunks->count();
    }

    /**
     * The questions of the chunks that have them, embedded in one request.
     *
     * @param  list<Chunk>  $chunks
     * @return array<int, array<int, float>> chunk id => vector
     */
    private function questionVectors(array $chunks): array
    {
        $asked = array_values(array_filter($chunks, fn (Chunk $chunk): bool => filled($chunk->questions)));

        if ($asked === []) {
            return [];
        }

        return array_combine(
            array_map(fn (Chunk $chunk): int => $chunk->id, $asked),
            $this->embeddings->embedBatch(array_map(fn (Chunk $chunk): string => (string) $chunk->questions, $asked)),
        );
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function literal(array $vector): string
    {
        return '['.Arr::join($vector, ',').']';
    }
}
