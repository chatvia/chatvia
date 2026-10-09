<?php

namespace App\Console\Commands;

use Domain\Billing\Actions\Credits\RefreshCreditCachesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled refresh of every workspace's cached message-credit figures.
 */
#[Description('Refresh the cached can-send-messages flag and sent-messages counter for workspaces with an active credit cycle.')]
#[Signature('app:cache-message-usage')]
class CacheMessageUsage extends Command
{
    public function handle(RefreshCreditCachesAction $refresh): int
    {
        $refreshed = $refresh->execute();

        $this->info("Refreshed message credits for {$refreshed} workspace(s).");

        return self::SUCCESS;
    }
}
