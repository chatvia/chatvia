<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Domain\Billing\Data\Resources\Package\BillingCurrentPackageData;
use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Domain\Billing\Services\Catalog\PlanFeatureList;
use Illuminate\Support\Collection;

/**
 * Read-only view of the plans in {@see BillingCatalog}: look a plan up by key
 * or Stripe price, and add the display fields (formatted prices, yearly
 * savings, feature bullets) the pricing pages need. A plan comes back as an
 * array in the shape the billing pages read.
 *
 * The key of a plan (`starter`, `pro`, …) is the stable plan id; the display
 * name can be changed in the admin panel, so nothing is matched by name.
 * Archived plans are left out of the plans a workspace can
 * choose, but still resolve by key and by Stripe price for the workspaces
 * subscribed to them.
 */
class PlanCatalog
{
    public function __construct(
        private readonly BillingCatalog $catalog,
        private readonly PlanFeatureList $features,
    ) {}

    /**
     * Every plan a workspace can choose, with its display fields and
     * capabilities, keyed by plan id.
     *
     * @return Collection<string, non-empty-array<string, mixed>>
     */
    public function availablePackages(): Collection
    {
        return $this->catalog->activePlans()->map($this->toPackage(...));
    }

    /**
     * One plan with its display fields (archived plans included), or null
     * when there is no plan with this key.
     *
     * @return array<string, mixed>|null
     */
    public function package(string $packageId): ?array
    {
        $plan = $this->catalog->plan($packageId);

        return $plan instanceof BillingPlan ? $this->toPackage($plan) : null;
    }

    /**
     * The plan new accounts start on (the plan marked as the default).
     *
     * @return array<string, mixed>|null
     */
    public function defaultPackage(): ?array
    {
        $plan = $this->catalog->defaultPlan();

        return $plan instanceof BillingPlan ? $this->toPackage($plan) : null;
    }

    /**
     * The plan a workspace is on: the one its plan subscription's Stripe price
     * belongs to, or the default plan when it has no subscription.
     *
     * @return array<string, mixed>
     */
    public function currentPackage(?UserBilling $billing): array
    {
        $stripePrice = $billing?->subscription()?->stripe_price;

        return $stripePrice !== null
            ? $this->packageForPrice($stripePrice)
            : $this->defaultPackage() ?? [];
    }

    /**
     * The plan a subscription's Stripe price belongs to, with `is_yearly` set
     * and the Stripe price ids removed. A price no plan has (current or
     * legacy) falls back to the default plan, so a page never breaks on an
     * old subscription.
     *
     * @return array<string, mixed>
     */
    public function packageForPrice(string $priceId): array
    {
        $selection = $this->planForPrice($priceId);
        $package = $selection instanceof PlanSelectionData ? $this->package($selection->package) : null;

        if (! $selection instanceof PlanSelectionData || $package === null) {
            return $this->defaultPackage() ?? [];
        }

        unset($package['stripe_monthly_price_id'], $package['stripe_yearly_price_id']);

        return [...$package, 'is_yearly' => $selection->isYearly];
    }

    /**
     * The plan and billing cycle behind a Stripe price (a current or a legacy
     * price of the plan), or null when the price is not a plan price (an
     * add-on price, or an unknown one). Unlike {@see packageForPrice()} this
     * never falls back to the default plan, so a caller can tell "unknown
     * price" apart from "starter".
     */
    public function planForPrice(?string $priceId): ?PlanSelectionData
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        return $this->catalog->planForPrice($priceId);
    }

    /**
     * The default plan's fixed (cycle-independent) limit for one quota, e.g.
     * `teams_limit`. Every quota gate falls back to it for an account that has
     * no `user_quotas` row yet (a fresh sign-up before the first plan grant).
     */
    public function defaultFixedLimit(string $key, int $default = 0): int
    {
        return $this->catalog->defaultPlan()?->quota_limits['fixed'][$key] ?? $default;
    }

    /**
     * The icon name of every plan, keyed by plan id.
     *
     * @return array<string, string>
     */
    public function packageIcons(): array
    {
        return $this->catalog->plans()
            ->map(fn (BillingPlan $plan): ?string => $plan->icon)
            ->filter()
            ->all();
    }

    /**
     * A plan in the shape the billing pages and {@see BillingCurrentPackageData}
     * read.
     *
     * @return non-empty-array<string, mixed>
     */
    private function toPackage(BillingPlan $plan): array
    {
        $monthlyPrice = $plan->monthly_price_cents;
        $yearlyPrice = $plan->yearly_price_cents ?? 0;

        return [
            'id' => $plan->key,
            'name' => $plan->name,
            'description' => $plan->description ?? '',
            'features' => $this->features->forPlan($plan),
            'stripe_monthly_price_id' => $plan->stripe_monthly_price_id,
            'stripe_yearly_price_id' => $plan->stripe_yearly_price_id,
            'monthly_price' => $monthlyPrice,
            'yearly_price' => $yearlyPrice,
            'is_popular' => $plan->is_popular,
            'contact_sales' => $plan->contact_sales,
            'capabilities' => $plan->capabilities->toArray(),
            'quota_limits' => $plan->quota_limits,
            'monthly_price_formatted' => $this->formatPrice($monthlyPrice),
            'yearly_price_formatted' => $this->formatPrice($yearlyPrice),
            'monthly_savings' => $this->yearlySavings($monthlyPrice, $yearlyPrice),
        ];
    }

    private function formatPrice(int $priceInCents): string
    {
        return config('billing.currency_symbol', '$').number_format($priceInCents / 100, 2);
    }

    /**
     * What paying yearly saves against twelve monthly payments, with the
     * whole-number percentage the plan cards show ("Save 20%"). Zero when
     * the plan has no price for one of the cycles or the yearly price saves
     * nothing.
     *
     * @return array{amount: int, percentage: int, formatted: string}
     */
    private function yearlySavings(int $monthlyPrice, int $yearlyPrice): array
    {
        if ($monthlyPrice === 0 || $yearlyPrice === 0) {
            return ['amount' => 0, 'percentage' => 0, 'formatted' => $this->formatPrice(0)];
        }

        $yearlyEquivalent = $monthlyPrice * 12;
        $savings = $yearlyEquivalent - $yearlyPrice;

        return [
            'amount' => $savings,
            'percentage' => max(0, (int) round(($savings / $yearlyEquivalent) * 100)),
            'formatted' => $this->formatPrice($savings),
        ];
    }
}
