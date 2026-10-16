<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Enums;

/**
 * The pgvector type the embedding columns use. `halfvec` stores 2 bytes per
 * dimension, half the space of `vector` (4 bytes), with almost the same
 * search quality. The values are pgvector type names.
 */
enum VectorType: string
{
    case Halfvec = 'halfvec';
    case Vector = 'vector';

    /** Operator class of an HNSW index on a column of this type (L2 distance). */
    public function operatorClass(): string
    {
        return match ($this) {
            self::Halfvec => 'halfvec_l2_ops',
            self::Vector => 'vector_l2_ops',
        };
    }

    /** The most dimensions pgvector's HNSW index accepts for this type. */
    public function maxIndexedDimensions(): int
    {
        return match ($this) {
            self::Halfvec => 4000,
            self::Vector => 2000,
        };
    }
}
