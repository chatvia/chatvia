<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Data;

use Spatie\LaravelData\Data;

/**
 * What a vchordrq index on chunks looks like right now: its partitions and
 * the row count it was built for.
 */
final class VectorIndexStateData extends Data
{
    public function __construct(
        /** @var list<int> the partitions per level; empty for a flat index */
        public array $lists,
        /** Rows the index was built for (kept in its comment); null when unknown. */
        public ?int $builtForRows,
    ) {}
}
