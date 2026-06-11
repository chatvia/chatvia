<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Description('Rebuild the vchordrq vector indexes on chunks so their k-means partitioning is retrained on the CURRENT rows.')]
#[Signature('vector:reindex')]
class VectorReindex extends Command
{
    /**
     * vchordrq trains its k-means centroids at CREATE INDEX time, sampling the
     * rows that exist THEN. migrate:fresh builds both indexes on an empty
     * table, so the initial partitioning is degenerate — every later insert
     * lands in meaningless lists and ANN recall collapses as the table grows.
     * REINDEX re-runs the internal build against the live data. Run after the
     * first bulk ingestion and after each ~10x row growth; also scheduled
     * weekly (routes/console.php) as a safety net during the growth phase.
     *
     * CONCURRENTLY keeps retrieval (every chat message) and ingestion writes
     * unblocked while the rebuild runs.
     */
    private const array INDEXES = [
        'chunks_embedding_vchordrq_optimized',
        'chunks_questions_embedding_vchordrq',
    ];

    public function handle(): int
    {
        foreach (self::INDEXES as $index) {
            $this->info("Reindexing {$index} …");
            $started = microtime(true);

            DB::statement("REINDEX INDEX CONCURRENTLY {$index}");

            $this->info(sprintf('  done in %.1fs', microtime(true) - $started));
        }

        return self::SUCCESS;
    }
}
