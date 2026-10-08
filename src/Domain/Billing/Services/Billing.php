<?php

namespace Domain\Billing\Services;

use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Illuminate\Support\Collection;

class Billing
{
    public function getPackageByPriceId(string $priceId, bool $removeStripePriceIds = false): array
    {
        $packages = collect(config('billing.packages'));

        // Match by price, keeping the config KEY — that key is the stable plan id
        // (consumed by forPlan() + the capability resolver). Deriving the id from
        // the display name instead would break the moment a name is rebranded.
        $key = $packages->search(function ($package) use ($priceId) {
            return $package['stripe_monthly_price_id'] === $priceId || $package['stripe_yearly_price_id'] === $priceId;
        });

        // The subscription may point at a price no longer in the billing config
        // (an archived/legacy Stripe price). Degrade to the default package
        // rather than dereferencing null and 500-ing every request.
        if ($key === false) {
            return $this->getFreePackage() ?? [];
        }

        $package = $packages->get($key);
        $package['is_yearly'] = $package['stripe_yearly_price_id'] === $priceId;

        if ($removeStripePriceIds) {
            unset($package['stripe_monthly_price_id'], $package['stripe_yearly_price_id']);
        }

        return array_merge($package, [
            'id' => $key,
            'monthly_price_formatted' => $this->formatPrice($package['monthly_price']),
            'yearly_price_formatted' => $this->formatPrice($package['yearly_price']),
            'monthly_savings' => $this->calculateSavings($package['monthly_price'], $package['yearly_price']),
        ]);
    }

    /**
     * The plan key and billing cycle behind a Stripe price, or null when the
     * price is not one of the configured plan prices (an add-on price, or an
     * archived price that is no longer in the config). Unlike
     * {@see getPackageByPriceId()} this never falls back to the default
     * package, so a caller can tell "unknown price" apart from "starter".
     *
     * @return array{package: string, is_yearly: bool}|null
     */
    public function planForPriceId(?string $priceId): ?array
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        foreach ((array) config('billing.packages', []) as $key => $package) {
            if (($package['stripe_monthly_price_id'] ?? null) === $priceId) {
                return ['package' => (string) $key, 'is_yearly' => false];
            }

            if (($package['stripe_yearly_price_id'] ?? null) === $priceId) {
                return ['package' => (string) $key, 'is_yearly' => true];
            }
        }

        return null;
    }

    public function getFreePackage(): ?array
    {
        // Since free package is removed, return starter as default
        return $this->getPackage(config('billing.default_package', 'starter'));
    }

    /**
     * The default package's fixed (cycle-independent) quota limit for $key —
     * e.g. 'teams_limit', 'workspace_users_limit'. The fallback every quota
     * gate uses for accounts with no user_quotas row yet (fresh signup before
     * the first plan/webhook lands one). Keeps the billing-config tree shape
     * out of the auth model.
     */
    public function defaultFixedLimit(string $key, int $default = 0): int
    {
        return (int) config(
            'billing.packages.'.config('billing.default_package', 'starter').".quota_limits.fixed.{$key}",
            $default,
        );
    }

    public function getPackageByName(string $name)
    {
        $packages = $this->getAvailablePackages();

        return $packages->where('name', ucfirst($name))->first();
    }

    public function getAvailablePackages(): Collection
    {
        $packages = config('billing.packages');

        return collect($packages)->map(function ($package, $key) {
            return array_merge($package, [
                'id' => $key,
                'monthly_price_formatted' => $this->formatPrice($package['monthly_price']),
                'yearly_price_formatted' => $this->formatPrice($package['yearly_price']),
                'monthly_savings' => $this->calculateSavings($package['monthly_price'], $package['yearly_price']),
                'capabilities' => BillingPackageCapabilitiesData::forPlan($key)->toArray(),
                'features_monthly' => $package['features']['monthly'] ?? [],
                'features_yearly' => $package['features']['yearly'] ?? [],
            ]);
        });
    }

    private function formatPrice(int $priceInCents): string
    {
        $symbol = config('billing.currency_symbol', '$');
        $price = $priceInCents / 100;

        return $symbol.number_format($price, 2);
    }

    private function calculateSavings(int $monthlyPrice, int $yearlyPrice): array
    {
        if ($monthlyPrice === 0 || $yearlyPrice === 0) {
            return ['amount' => 0, 'percentage' => 0, 'formatted' => $this->formatPrice(0)];
        }

        $yearlyEquivalent = $monthlyPrice * 12;
        $savings = $yearlyEquivalent - $yearlyPrice;
        $percentage = round(($savings / $yearlyEquivalent) * 100);

        return [
            'amount' => $savings,
            'percentage' => $percentage,
            'formatted' => $this->formatPrice($savings),
        ];
    }

    public function getPackage(string $packageId): ?array
    {
        $package = config("billing.packages.{$packageId}");

        if (! $package) {
            return null;
        }

        return array_merge($package, [
            'id' => $packageId,
            'monthly_price_formatted' => $this->formatPrice($package['monthly_price']),
            'yearly_price_formatted' => $this->formatPrice($package['yearly_price']),
            'monthly_savings' => $this->calculateSavings($package['monthly_price'], $package['yearly_price']),
        ]);
    }
}
