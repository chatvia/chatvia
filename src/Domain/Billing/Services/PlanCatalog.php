<?php

namespace Domain\Billing\Services;

use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Models\UserBilling;
use Illuminate\Support\Collection;

/**
 * Read-only view of the plans in `config('billing.packages')`: look a plan up
 * by key, name or Stripe price, and add the display fields (formatted prices,
 * yearly savings) the pricing pages need.
 *
 * The config key of a package (`starter`, `pro`, …) is the stable plan id. The
 * display name may change, so nothing is matched by name except the plan-change
 * form, which posts names.
 */
class PlanCatalog
{
    /**
     * Every plan with its display fields and capabilities, keyed by plan id.
     *
     * @return Collection<string, non-empty-array<string, mixed>>
     */
    public function availablePackages(): Collection
    {
        return collect($this->packages())->map(fn (array $package, string $key): array => [
            ...$this->withDisplayFields($package, $key),
            'capabilities' => BillingPackageCapabilitiesData::forPlan($key)->toArray(),
            'features_monthly' => $package['features']['monthly'] ?? [],
            'features_yearly' => $package['features']['yearly'] ?? [],
        ]);
    }

    /**
     * One plan with its display fields, or null when the key is not configured.
     *
     * @return array<string, mixed>|null
     */
    public function package(string $packageId): ?array
    {
        $package = $this->packages()[$packageId] ?? null;

        return is_array($package) ? $this->withDisplayFields($package, $packageId) : null;
    }

    /**
     * The plan new accounts start on (`billing.default_package`).
     *
     * @return array<string, mixed>|null
     */
    public function defaultPackage(): ?array
    {
        return $this->package($this->defaultPackageId());
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
     * and the Stripe price ids removed. A price that is no longer in the config
     * (an archived or legacy price) falls back to the default plan, so a page
     * never breaks on an old subscription.
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
     * The plan and billing cycle behind a Stripe price, or null when the price
     * is not a configured plan price (an add-on price, or an archived one).
     * Unlike {@see packageForPrice()} this never falls back to the default
     * plan, so a caller can tell "unknown price" apart from "starter".
     */
    public function planForPrice(?string $priceId): ?PlanSelectionData
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        foreach ($this->packages() as $key => $package) {
            if (($package['stripe_monthly_price_id'] ?? null) === $priceId) {
                return new PlanSelectionData(package: (string) $key, isYearly: false);
            }

            if (($package['stripe_yearly_price_id'] ?? null) === $priceId) {
                return new PlanSelectionData(package: (string) $key, isYearly: true);
            }
        }

        return null;
    }

    /**
     * A plan by its display name (case-insensitive on the first letter, as the
     * plan-change form posts lower-case names).
     *
     * @return array<string, mixed>|null
     */
    public function packageByName(string $name): ?array
    {
        return $this->availablePackages()->firstWhere('name', ucfirst($name));
    }

    /**
     * The default plan's fixed (cycle-independent) limit for one quota, e.g.
     * `teams_limit`. Every quota gate falls back to it for an account that has
     * no `user_quotas` row yet (a fresh sign-up before the first plan grant).
     */
    public function defaultFixedLimit(string $key, int $default = 0): int
    {
        return (int) config("billing.packages.{$this->defaultPackageId()}.quota_limits.fixed.{$key}", $default);
    }

    private function defaultPackageId(): string
    {
        return (string) config('billing.default_package', 'starter');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function packages(): array
    {
        return (array) config('billing.packages', []);
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    private function withDisplayFields(array $package, string $packageId): array
    {
        return [
            ...$package,
            'id' => $packageId,
            'monthly_price_formatted' => $this->formatPrice($package['monthly_price']),
            'yearly_price_formatted' => $this->formatPrice($package['yearly_price']),
            'monthly_savings' => $this->yearlySavings($package['monthly_price'], $package['yearly_price']),
        ];
    }

    private function formatPrice(int $priceInCents): string
    {
        return config('billing.currency_symbol', '$').number_format($priceInCents / 100, 2);
    }

    /**
     * @return array{amount: int, percentage: float|int, formatted: string}
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
            'percentage' => round(($savings / $yearlyEquivalent) * 100),
            'formatted' => $this->formatPrice($savings),
        ];
    }
}
