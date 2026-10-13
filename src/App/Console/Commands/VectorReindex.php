<?php

namespace App\Console\Commands;

use Domain\Knowledge\Retrieval\Actions\RebuildVectorIndexAction;
use Domain\Knowledge\Retrieval\Enums\VectorIndexRebuild;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sizes the vchordrq vector indexes on chunks for the current rows, one by
 * one with progress output, rebuilding the ones that need it. See
 * {@see RebuildVectorIndexAction} for when and why.
 */
#[Description('Size the vchordrq vector indexes on chunks for the current rows; rebuild the ones whose rows doubled or that must change shape.')]
#[Signature('vector:reindex {--force : Rebuild every index, even when it fits the rows}')]
class VectorReindex extends Command
{
    public function handle(RebuildVectorIndexAction $rebuild): int
    {
        $when = $this->option('force') ? VectorIndexRebuild::Always : VectorIndexRebuild::WhenGrown;

        foreach (array_keys(RebuildVectorIndexAction::INDEXES) as $index) {
            $this->info("Checking {$index} …");
            $started = microtime(true);

            $result = $rebuild->execute($index, $when);

            $layout = $result->lists === [] ? 'flat' : 'lists ['.implode(', ', $result->lists).']';
            $this->info($result->rebuilt
                ? sprintf('  rebuilt for %d rows (%s) in %.1fs', $result->rows, $layout, microtime(true) - $started)
                : sprintf('  kept: %d rows (%s)', $result->rows, $layout));
        }

        return self::SUCCESS;
    }
}
