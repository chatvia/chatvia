<?php

declare(strict_types=1);

namespace App\Console\Commands\Llm;

use Domain\Llm\Actions\Catalog\RecheckUnlistedLlmsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs the hourly recheck of the models a provider stopped serving
 * ({@see RecheckUnlistedLlmsAction}), so one it serves again is offered again.
 */
#[Description('Check whether the providers serve the models marked unlisted again.')]
#[Signature('chatvia:llm:check-listing')]
class CheckLlmListing extends Command
{
    public function handle(RecheckUnlistedLlmsAction $recheck): int
    {
        $providers = $recheck->execute();

        $this->info("Checked {$providers} providers.");

        return self::SUCCESS;
    }
}
