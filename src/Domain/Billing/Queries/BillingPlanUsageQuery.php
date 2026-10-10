<?php

declare(strict_types=1);

namespace Domain\Billing\Queries;

use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;

/**
 * Which workspaces use a plan, for the admin panel: how many are subscribed
 * to each plan now, and whether any workspace ever was (a plan that was ever
 * used must stay, archived, so old subscriptions and invoices still resolve).
 *
 * A subscription is on a plan when its Stripe price is one of the plan's
 * current or legacy prices.
 */
class BillingPlanUsageQuery
{
    public function __construct(
        private readonly BillingCatalog $catalog,
    ) {}

    /**
     * Active plan subscriptions (trials included) per plan key, in one grouped
     * query. Plans without subscribers are left out.
     *
     * @return array<string, int>
     */
    public function subscriberCounts(): array
    {
        $perPrice = Subscription::query()
            ->where('type', 'default')
            ->active()
            ->whereNotNull('stripe_price')
            ->groupBy('stripe_price')
            ->selectRaw('stripe_price, count(*) as subscribers')
            ->pluck('subscribers', 'stripe_price');

        $perPlan = [];

        foreach ($perPrice as $priceId => $subscribers) {
            $plan = $this->catalog->planForPrice((string) $priceId);

            if ($plan instanceof PlanSelectionData) {
                $perPlan[$plan->package] = ($perPlan[$plan->package] ?? 0) + (int) $subscribers;
            }
        }

        return $perPlan;
    }

    /** Whether a workspace is or ever was on the plan, by its label or by a subscription on one of its prices. */
    public function wasEverUsed(BillingPlan $plan): bool
    {
        if (UserBilling::query()->where('plan', $plan->key)->exists()) {
            return true;
        }

        $priceIds = array_values(array_filter([
            $plan->stripe_monthly_price_id,
            $plan->stripe_yearly_price_id,
            ...array_keys($plan->legacy_stripe_price_ids),
        ], filled(...)));

        if ($priceIds === []) {
            return false;
        }

        return Subscription::query()->whereIn('stripe_price', $priceIds)->exists()
            || SubscriptionItem::query()->whereIn('stripe_price', $priceIds)->exists();
    }
}
