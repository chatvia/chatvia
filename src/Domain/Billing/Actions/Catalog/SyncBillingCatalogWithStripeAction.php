<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Catalog;

use Domain\Billing\Data\Catalog\StripeCatalogSyncData;
use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Queries\BillingCatalogItemsQuery;
use Domain\Billing\Services\Catalog\StripeCatalogSync;

/**
 * Sends every plan and add-on, archived ones included, to Stripe
 * ({@see StripeCatalogSync}): the "Sync with Stripe" button of the admin
 * panel. Plans and add-ons whose Stripe prices already charge the saved
 * amounts are left as they are, so running it twice creates nothing the
 * second time. The sync stops at the first Stripe error and reports it.
 */
class SyncBillingCatalogWithStripeAction
{
    public function __construct(
        private readonly StripeCatalogSync $stripe,
        private readonly BillingCatalogItemsQuery $items,
    ) {}

    public function execute(): StripeCatalogSyncData
    {
        $blocker = $this->stripe->blocker();

        if ($blocker instanceof StripeSyncBlocker) {
            return StripeCatalogSyncData::blocked($blocker);
        }

        $result = new StripeCatalogSyncData;

        foreach ($this->items->get() as $item) {
            $result = $result->merge($item instanceof BillingPlan ? $this->stripe->plan($item) : $this->stripe->addon($item));

            if ($result->failed()) {
                break;
            }
        }

        return $result;
    }
}
