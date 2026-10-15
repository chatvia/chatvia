<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Actions\Reindex;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\ChunkEmbeddingText;
use Illuminate\Support\Facades\DB;

/**
 * Embeds the chunks in one id range again with the embedding model in use:
 * the chunk from the same text ingestion embedded ({@see ChunkEmbeddingText}),
 * and its questions when it has them. One part of a re-index
 * ({@see StartEmbeddingReindexAction}). The text and the keyword (BM25)
 * columns stay as they are.
 */
class ReembedChunksAction
{
    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    /**
     * @return int how many chunks were embedded again
     */
    public function execute(int $firstId, int $lastId): int
    {
        $chunks = Chunk::query()
            ->whereBetween('id', [$firstId, $lastId])
            ->orderBy('id')
            ->get(['id', 'chunk_index', 'chunk', 'summary', 'questions', 'metadata'])
            ->values();

        if ($chunks->isEmpty()) {
            return 0;
        }

        $vectors = $this->embeddings->embedBatch($chunks->map(ChunkEmbeddingText::of(...))->all());
        $questionVectors = $this->questionVectors($chunks->all());

        DB::transaction(function () use ($chunks, $vectors, $questionVectors): void {
            foreach ($chunks as $position => $chunk) {
                Chunk::query()->whereKey($chunk->id)->toBase()->update([
                    'embedding' => $this->literal($vectors[$position]),
                    'questions_embedding' => isset($questionVectors[$chunk->id]) ? $this->literal($questionVectors[$chunk->id]) : null,
                ]);
            }
        });

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
        return '['.implode(',', $vector).']';
    }
}
