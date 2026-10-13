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

    /** The vector size the column holds: 1024 for `halfvec(1024)`. */
    public function dimensions(): ?int
    {
        return preg_match('/\((\d+)\)$/', $this->type, $match) === 1 ? (int) $match[1] : null;
    }
}
