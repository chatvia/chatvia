<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Enums;

/**
 * What the HNSW indexes on the chunk embeddings hold. `binary` indexes one
 * bit per dimension (`binary_quantize()`), about 6× smaller than the vectors
 * themselves, and search re-scores its candidates with the stored vectors;
 * `full` indexes the vectors as stored. Unlike the column type it can change
 * after install: `chatvia:search:vector-indexes` rebuilds the indexes.
 */
enum VectorIndex: string
{
    case Binary = 'binary';
    case Full = 'full';
}
