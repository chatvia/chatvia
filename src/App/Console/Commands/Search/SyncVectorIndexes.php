<?php

declare(strict_types=1);

namespace App\Console\Commands\Search;

use Domain\Knowledge\Retrieval\Actions\ConfigureVectorIndexesAction;
use Domain\Knowledge\Retrieval\Services\ChunkVectorIndex;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Start-up and deploy step, run after the migrations: builds the chunk HNSW
 * indexes in the kind EMBEDDING_INDEX names when they are in the other kind
 * (or missing), and does nothing when they already match. A rebuild reads
 * every chunk, so it takes a while on a large knowledge base.
 */
#[Description('Rebuild the chunk vector indexes when they do not match EMBEDDING_INDEX (binary or full).')]
#[Signature('chatvia:search:vector-indexes')]
class SyncVectorIndexes extends Command
{
    public function handle(ConfigureVectorIndexesAction $configure, ChunkVectorIndex $shape): int
    {
        $built = $configure->execute();

        $this->components->info($built->isEmpty()
            ? "The vector indexes already match EMBEDDING_INDEX ({$shape->kind()->value})."
            : "Rebuilt as {$shape->kind()->value}: {$built->implode(', ')}.");

        return self::SUCCESS;
    }
}
