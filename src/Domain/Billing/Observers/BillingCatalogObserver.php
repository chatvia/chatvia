<?php

declare(strict_types=1);

namespace Domain\Billing\Observers;

use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Clears the cached billing catalog whenever a plan or add-on is saved or
 * deleted, so the next read sees the change.
 *
 * It runs after the database transaction commits: clearing earlier would let
 * another request cache the old rows again before the new ones are visible.
 */
class BillingCatalogObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly BillingCatalog $catalog,
    ) {}

    public function saved(BillingPlan|BillingAddon $model): void
    {
        $this->catalog->flush();
    }

    public function deleted(BillingPlan|BillingAddon $model): void
    {
        $this->catalog->flush();
    }
}
