<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rebuilds one vchordrq vector index on `chunks` so its k-means partitioning
 * is trained on the current rows.
 *
 * vchordrq trains its centroids when the index is created, from the rows that
 * exist then. An index built on an empty table is partitioned at random, so
 * later rows land in meaningless lists and recall drops as the table grows.
 * Rebuild after the first bulk ingestion and after each ~10x growth (also
 * scheduled weekly). CONCURRENTLY keeps search and ingestion running meanwhile.
 */
class RebuildVectorIndexAction
{
    /** The vchordrq indexes on chunks. */
    public const array INDEXES = [
        'chunks_embedding_vchordrq_optimized',
        'chunks_questions_embedding_vchordrq',
    ];

    public function execute(string $index): void
    {
        // The name is interpolated (REINDEX takes no bindings), so only the
        // known indexes are accepted.
        if (! in_array($index, self::INDEXES, true)) {
            throw new InvalidArgumentException("Unknown vector index: {$index}");
        }

        DB::statement("REINDEX INDEX CONCURRENTLY {$index}");
    }
}
