<?php

namespace App\Console\Commands;

use Domain\Knowledge\Retrieval\Actions\RebuildVectorIndexAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rebuilds the vchordrq vector indexes on chunks, one by one with progress
 * output. See {@see RebuildVectorIndexAction} for when and why.
 */
#[Description('Rebuild the vchordrq vector indexes on chunks so their k-means partitioning is retrained on the CURRENT rows.')]
#[Signature('vector:reindex')]
class VectorReindex extends Command
{
    public function handle(RebuildVectorIndexAction $rebuild): int
    {
        foreach (RebuildVectorIndexAction::INDEXES as $index) {
            $this->info("Reindexing {$index} …");
            $started = microtime(true);

            $rebuild->execute($index);

            $this->info(sprintf('  done in %.1fs', microtime(true) - $started));
        }

        return self::SUCCESS;
    }
}
