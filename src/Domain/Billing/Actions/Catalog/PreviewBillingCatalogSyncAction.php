<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Catalog;

use Domain\Billing\Data\Catalog\StripeCatalogSyncData;
use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Queries\BillingCatalogItemsQuery;
use Domain\Billing\Services\Catalog\StripeCatalogSync;

/**
 * "Check against Stripe": compares every plan and add-on with the live Stripe
 * products and prices and reports what {@see SyncBillingCatalogWithStripeAction}
 * would do, without writing anything. Above all it lists every saved amount
 * that differs from what Stripe charges today, which a sync would turn into a
 * new price for new customers.
 *
 * When two add-ons share one Stripe product, the preview cannot claim it for
 * the first one, so it may report one new product fewer than the sync makes.
 */
class PreviewBillingCatalogSyncAction
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
            $result = $result->merge($item instanceof BillingPlan ? $this->stripe->previewPlan($item) : $this->stripe->previewAddon($item));

            if ($result->failed()) {
                break;
            }
        }

        return $result;
    }
}
