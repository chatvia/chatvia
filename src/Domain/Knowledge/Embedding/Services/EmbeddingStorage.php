<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Services;

use Domain\Knowledge\Embedding\Enums\VectorType;
use Domain\Knowledge\Embedding\Exceptions\InvalidEmbeddingStorageException;

/**
 * How the database stores embeddings: the pgvector type and the size of every
 * embedding column (`chatvia.embeddings`). An install chooses them once,
 * before its first migration, because the columns and their indexes are
 * created with them. `chatvia:search:check-embedding-storage` stops a start whose
 * settings no longer match the database.
 */
final class EmbeddingStorage
{
    public function type(): VectorType
    {
        $value = config('chatvia.embeddings.type');

        return VectorType::tryFrom(is_string($value) ? strtolower(trim($value)) : '')
            ?? throw InvalidEmbeddingStorageException::type($value);
    }

    /**
     * Capped at what pgvector's HNSW index accepts for the type (2000 for
     * `vector`, 4000 for `halfvec`): the chunks indexes are created with this
     * size. Providers with larger native sizes support shortening (MRL).
     */
    public function dimensions(): int
    {
        $value = config('chatvia.embeddings.dimensions');
        $dimensions = filter_var($value, FILTER_VALIDATE_INT);

        if ($dimensions === false || $dimensions < 1 || $dimensions > $this->type()->maxIndexedDimensions()) {
            throw InvalidEmbeddingStorageException::dimensions($value);
        }

        return $dimensions;
    }

    /**
     * The column type as PostgreSQL names it, e.g. `halfvec(1024)`. Also the
     * cast for a query vector compared with a stored one.
     */
    public function columnType(): string
    {
        return "{$this->type()->value}({$this->dimensions()})";
    }
}
