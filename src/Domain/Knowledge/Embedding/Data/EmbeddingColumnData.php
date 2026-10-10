<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Data;

use Spatie\LaravelData\Data;

/**
 * One embedding column as it exists in the database, with its type as
 * PostgreSQL names it (`halfvec(1024)`).
 */
final class EmbeddingColumnData extends Data
{
    public function __construct(
        public string $table,
        public string $column,
        public string $type,
    ) {}
}
