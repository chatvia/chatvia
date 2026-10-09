<?php

namespace Domain\Billing\Services;

use Domain\Billing\Actions\Webhooks\GrantRenewalAction;
use Domain\Billing\Data\Stripe\PaidInvoiceData;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Models\UserBilling;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;

/**
 * Works out which plan and billing cycle a renewal invoice paid for (used by
 * {@see GrantRenewalAction}), or null when the invoice renews an add-on
 * subscription instead of the plan.
 *
 * The Stripe price is the source of truth: first the invoice's own
 * subscription lines, then the locally synced Cashier subscription. The
 * subscription metadata is only a last resort for a price that is not in the
 * billing config, because applying a scheduled plan change clears its
 * `to_package` and `is_yearly` keys.
 */
class RenewedPlanResolver
{
    public function __construct(
        private readonly PlanCatalog $plans,
    ) {}

    public function resolve(PaidInvoiceData $invoice, UserBilling $billing, ?Subscription $subscription): ?PlanSelectionData
    {
        $metadata = $invoice->subscriptionMetadata ?? new StripeMetadata;

        $plan = $this->planForAnyPrice($invoice->linePriceIds);

        if ($plan instanceof PlanSelectionData) {
            return $plan;
        }

        if ($this->isAddonInvoice($invoice, $metadata, $billing)) {
            return null;
        }

        return $this->planForAnyPrice($this->localPriceIds($subscription))
            ?? $this->planFromMetadata($invoice, $metadata, $billing);
    }

    /**
     * @param  list<string|null>  $priceIds
     */
    private function planForAnyPrice(array $priceIds): ?PlanSelectionData
    {
        foreach ($priceIds as $priceId) {
            $plan = $this->plans->planForPrice($priceId);

            if ($plan instanceof PlanSelectionData) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Every add-on checkout tags its subscription (see {@see StripeMetadata}),
     * and every add-on gets its own non-default Cashier subscription row.
     */
    private function isAddonInvoice(PaidInvoiceData $invoice, StripeMetadata $metadata, UserBilling $billing): bool
    {
        if ($metadata->isAddonSubscription()) {
            return true;
        }

        if ($invoice->subscriptionId === null) {
            return false;
        }

        return $billing->subscriptions()
            ->where('stripe_id', $invoice->subscriptionId)
            ->where('type', '!=', 'default')
            ->exists();
    }

    /**
     * @return list<string|null>
     */
    private function localPriceIds(?Subscription $subscription): array
    {
        if (! $subscription instanceof Subscription) {
            return [];
        }

        return [
            $subscription->stripe_price,
            ...$subscription->items()->pluck('stripe_price')->all(),
        ];
    }

    private function planFromMetadata(PaidInvoiceData $invoice, StripeMetadata $metadata, UserBilling $billing): PlanSelectionData
    {
        $package = $metadata->targetPackage()
            ?? $billing->plan
            ?? (string) config('billing.default_package', 'starter');

        Log::warning('Stripe renewal: no configured plan price found, using the subscription metadata', [
            'invoice_id' => $invoice->invoiceId,
            'customer' => $invoice->customerId,
            'package' => $package,
        ]);

        return new PlanSelectionData(package: $package, isYearly: $metadata->isYearly());
    }
}
