<?php

namespace App\Console\Commands;

use Domain\Billing\Actions\Subscriptions\SyncAddonQuotasAction;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Models\UserBilling;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Rebuild add-on quota columns (extra agents, members, custom domains) from the local Cashier subscriptions. Safe to run any time; it only writes what the subscriptions say.')]
#[Signature('billing:sync-addon-quotas')]
class SyncAddonQuotas extends Command
{
    public function handle(SyncAddonQuotasAction $syncAddonQuotas): int
    {
        $addonTypes = array_map(fn (Addon $addon): string => $addon->value, Addon::cases());
        $synced = 0;

        UserBilling::query()
            ->whereHas('subscriptions', fn ($query) => $query->whereIn('type', $addonTypes))
            ->with('user')
            ->chunkById(100, function ($billings) use ($syncAddonQuotas, &$synced): void {
                foreach ($billings as $billing) {
                    $syncAddonQuotas->execute($billing);
                    $synced++;
                }
            });

        $this->info("Synced add-on quotas for {$synced} workspace(s).");

        return self::SUCCESS;
    }
}
