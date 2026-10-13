<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Data;

use Spatie\LaravelData\Data;

/**
 * The outcome of one `vector:reindex` step for one index.
 */
final class VectorIndexRebuildData extends Data
{
    public function __construct(
        public string $index,
        /** Rows with a value in the indexed column. */
        public int $rows,
        /** @var list<int> the partitions the index has now; empty for a flat index */
        public array $lists,
        /** False when the index was kept as it was. */
        public bool $rebuilt,
    ) {}
}
