<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Billing\Actions\Addons\SyncAllAddonQuotasAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Repairs add-on quota columns from the local Cashier subscriptions.
 */
#[Description('Rebuild add-on quota columns (extra agents, members, custom domains) from the local Cashier subscriptions. Safe to run any time; it only writes what the subscriptions say.')]
#[Signature('billing:sync-addon-quotas')]
class SyncAddonQuotas extends Command
{
    public function handle(SyncAllAddonQuotasAction $syncAll): int
    {
        $synced = $syncAll->execute();

        $this->info("Synced add-on quotas for {$synced} workspace(s).");

        return self::SUCCESS;
    }
}
