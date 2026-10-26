<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Knowledge\Embedding\Enums\VectorIndex;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;

/**
 * The SQL shape of the HNSW indexes on the chunk embeddings, in the kind the
 * install chose (`EmbeddingStorage::index()`): over `binary_quantize()` of
 * each vector (Hamming distance), or over the vectors as stored (L2). The
 * index build and the search both read it, because the planner only walks an
 * index whose expression the ORDER BY repeats exactly.
 */
final readonly class ChunkVectorIndex
{
    public function __construct(private EmbeddingStorage $storage) {}

    public function kind(): VectorIndex
    {
        return $this->storage->index();
    }

    /** What the index is built on, for `CREATE INDEX … USING hnsw (…)`. */
    public function expression(string $column): string
    {
        return match ($this->kind()) {
            VectorIndex::Binary => "({$this->quantized($column)})",
            VectorIndex::Full => $column,
        };
    }

    public function operatorClass(): string
    {
        return match ($this->kind()) {
            VectorIndex::Binary => 'bit_hamming_ops',
            VectorIndex::Full => $this->storage->type()->operatorClass(),
        };
    }

    /**
     * The distance an indexed ORDER BY sorts by.
     *
     * @param  string  $queryVector  an SQL expression of the query vector, already cast
     */
    public function orderBy(string $column, string $queryVector): string
    {
        return match ($this->kind()) {
            VectorIndex::Binary => "{$this->quantized($column)} <~> {$this->quantized($queryVector)}",
            VectorIndex::Full => "{$column} <-> {$queryVector}",
        };
    }

    private function quantized(string $vector): string
    {
        return "binary_quantize({$vector})::bit({$this->storage->dimensions()})";
    }
}
