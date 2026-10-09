<?php

namespace Domain\Billing\Actions\Addons;

use Domain\Billing\Enums\Addon;
use Domain\Billing\Models\UserBilling;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rebuilds the add-on quota columns of every workspace that has (or had) an
 * add-on subscription, using {@see SyncAddonQuotasAction}. Safe to run at any
 * time: it only writes what the local Cashier subscriptions say. Returns how
 * many workspaces were synced.
 */
class SyncAllAddonQuotasAction
{
    public function __construct(
        private readonly SyncAddonQuotasAction $syncAddonQuotas,
    ) {}

    public function execute(): int
    {
        $addonTypes = array_map(fn (Addon $addon): string => $addon->value, Addon::cases());
        $synced = 0;

        UserBilling::query()
            ->whereHas('subscriptions', fn ($query) => $query->whereIn('type', $addonTypes))
            ->with('user')
            ->chunkById(100, function (Collection $billings) use (&$synced): void {
                foreach ($billings as $billing) {
                    $this->syncAddonQuotas->execute($billing);
                    $synced++;
                }
            });

        return $synced;
    }
}
