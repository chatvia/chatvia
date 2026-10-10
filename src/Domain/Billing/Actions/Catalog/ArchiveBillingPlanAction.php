<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Catalog;

use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Domain\Billing\Models\BillingPlan;

/**
 * Takes a plan off sale. An archived plan is no longer offered to anyone, but
 * it stays in the table: the workspaces subscribed to it keep it, and their
 * renewals still resolve to it. Nothing changes in Stripe. The default plan
 * cannot be archived, because new accounts start on it.
 *
 * @throws InvalidBillingCatalogException when the plan is the default plan
 */
class ArchiveBillingPlanAction
{
    public function execute(BillingPlan $plan): BillingPlan
    {
        if ($plan->is_default) {
            throw InvalidBillingCatalogException::defaultCannotBeArchived();
        }

        $plan->update(['is_active' => false]);

        return $plan;
    }
}
