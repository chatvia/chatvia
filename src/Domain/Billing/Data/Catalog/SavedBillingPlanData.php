<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Catalog;

use Domain\Billing\Models\BillingPlan;
use Spatie\LaravelData\Data;

/**
 * A plan that was just saved, and what sending it to Stripe did (or why it
 * was not sent).
 */
class SavedBillingPlanData extends Data
{
    public function __construct(
        public BillingPlan $plan,
        public StripeCatalogSyncData $stripe,
    ) {}
}
