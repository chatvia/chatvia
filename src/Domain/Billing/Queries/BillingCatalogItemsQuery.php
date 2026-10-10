<?php

declare(strict_types=1);

namespace Domain\Billing\Queries;

use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;

/**
 * Every plan and add-on, archived ones included, fresh from the database, in
 * the order a catalog-wide Stripe sync handles them: plans in display order,
 * then add-ons in the order they were added. The order matters when two
 * add-ons share one Stripe price today: the first one keeps its product.
 */
class BillingCatalogItemsQuery
{
    /**
     * @return list<BillingPlan|BillingAddon>
     */
    public function get(): array
    {
        return [
            ...BillingPlan::query()->orderBy('sort_order')->orderBy('id')->get()->all(),
            ...BillingAddon::query()->orderBy('id')->get()->all(),
        ];
    }
}
