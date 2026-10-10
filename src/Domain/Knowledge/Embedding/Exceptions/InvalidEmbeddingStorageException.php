<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Exceptions;

use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use RuntimeException;

/**
 * EMBEDDING_VECTOR_TYPE or EMBEDDING_DIMENSIONS holds a value the database
 * cannot store. Raised before any column is created or queried.
 */
class InvalidEmbeddingStorageException extends RuntimeException
{
    public static function type(mixed $value): self
    {
        return new self(sprintf(
            'EMBEDDING_VECTOR_TYPE must be "halfvec" or "vector", not "%s".',
            is_scalar($value) ? (string) $value : get_debug_type($value),
        ));
    }

    public static function dimensions(mixed $value): self
    {
        return new self(sprintf(
            'EMBEDDING_DIMENSIONS must be a whole number from 1 to %d, not "%s".',
            EmbeddingStorage::MAX_DIMENSIONS,
            is_scalar($value) ? (string) $value : get_debug_type($value),
        ));
    }
}
