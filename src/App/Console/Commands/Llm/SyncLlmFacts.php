<?php

declare(strict_types=1);

namespace App\Console\Commands\Llm;

use Domain\Llm\Actions\Catalog\SyncLlmFactsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs the daily sync of the models' facts from the models.dev catalog
 * ({@see SyncLlmFactsAction}): whether each model reasons, its effort values
 * and whether it takes a temperature. Does nothing while the lookup is off.
 */
#[Description('Update each model\'s facts (reasoning, effort values, temperature) from the models.dev catalog.')]
#[Signature('chatvia:llm:sync-facts')]
class SyncLlmFacts extends Command
{
    public function handle(SyncLlmFactsAction $syncFacts): int
    {
        $result = $syncFacts->execute();

        $this->info("Updated {$result->updated} models; {$result->notListed} not listed on models.dev.");

        return self::SUCCESS;
    }
}
