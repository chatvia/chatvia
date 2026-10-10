<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Queries;

use Domain\Knowledge\Embedding\Data\EmbeddingColumnData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every pgvector column (`vector` or `halfvec`) of the app's tables, read
 * from the PostgreSQL catalog, so a new embedding column is covered without
 * a list to keep up to date.
 */
final class StoredEmbeddingColumnsQuery
{
    /**
     * @return Collection<int, EmbeddingColumnData>
     */
    public function get(): Collection
    {
        $rows = DB::select(<<<'SQL'
SELECT c.relname AS table_name, a.attname AS column_name, format_type(a.atttypid, a.atttypmod) AS column_type
FROM pg_attribute a
JOIN pg_class c ON c.oid = a.attrelid
JOIN pg_namespace n ON n.oid = c.relnamespace
JOIN pg_type t ON t.oid = a.atttypid
WHERE t.typname IN ('vector', 'halfvec')
  AND c.relkind IN ('r', 'p')
  AND n.nspname = current_schema()
  AND a.attnum > 0
  AND NOT a.attisdropped
ORDER BY c.relname, a.attnum
SQL);

        return collect($rows)->map(fn (object $row): EmbeddingColumnData => new EmbeddingColumnData(
            table: (string) $row->table_name,
            column: (string) $row->column_name,
            type: (string) $row->column_type,
        ))->values();
    }
}
