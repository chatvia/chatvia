<?php

namespace App\Support;

use Illuminate\Support\Collection;

class Billing
{
    public function getPackageByPriceId(string $priceId, bool $removeStripePriceIds = false): array
    {
        $packages = collect(config('billing.packages'));

        $package = $packages->filter(function ($package) use ($priceId) {
            return $package['stripe_monthly_price_id'] == $priceId || $package['stripe_yearly_price_id'] === $priceId;
        })->map(function ($package) use ($priceId) {
            $package['is_yearly'] = $package['stripe_yearly_price_id'] === $priceId;

            return $package;
        })->first();

        if ($removeStripePriceIds) {
            unset($package['stripe_monthly_price_id'], $package['stripe_yearly_price_id']);
        }

        return array_merge($package, [
            'id' => strtolower($package['name']),
            'monthly_price_formatted' => $this->formatPrice($package['monthly_price']),
            'yearly_price_formatted' => $this->formatPrice($package['yearly_price']),
            'monthly_savings' => $this->calculateSavings($package['monthly_price'], $package['yearly_price']),
        ]);
    }

    public function getFreePackage()
    {
        return collect($this->getAvailablePackages())
            ->whereNull('stripe_monthly_price_id')
            ->whereNull('stripe_yearly_price_id')
            ->first();
    }

    public function getPackageByName(string $name)
    {
        $packages = $this->getAvailablePackages();

        return $packages->where('name', $name)->first();
    }

    public static function getPackages()
    {
        return config('billing.packages');
    }

    /**
     * Get capabilities for a specific package
     */
    public static function getCapabilitiesForPackage(string $packageKey): array
    {
        $packages = static::getPackages();
        return $packages[$packageKey]['capabilities'] ?? [];
    }

    /**
     * Check if a package has a specific capability
     */
    public static function packageHasCapability(string $packageKey, string $capability): bool
    {
        $capabilities = static::getCapabilitiesForPackage($packageKey);
        
        if (!isset($capabilities[$capability])) {
            return false;
        }

        $value = $capabilities[$capability];

        // Handle boolean capabilities
        if (is_bool($value)) {
            return $value;
        }

        // Handle string capabilities
        if (is_string($value)) {
            return $value !== false && $value !== 'false' && $value !== '';
        }

        // Handle numeric capabilities
        if (is_numeric($value)) {
            return $value > 0;
        }

        // Handle array capabilities
        if (is_array($value)) {
            return !empty($value);
        }

        return !empty($value);
    }

    /**
     * Get UI features for a specific package and billing cycle
     */
    public static function getFeaturesForPackage(string $packageKey, bool $isYearly = false): array
    {
        $packages = static::getPackages();
        $featuresConfig = $packages[$packageKey]['features'] ?? [];
        
        $billingCycle = $isYearly ? 'yearly' : 'monthly';
        return $featuresConfig[$billingCycle] ?? $featuresConfig['monthly'] ?? [];
    }

    /**
     * Get comparison features with their capability mappings
     */
    public static function getComparisonFeatures(): array
    {
        return config('billing.comparison_features', []);
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
                // Ensure capabilities are explicitly passed through
                'capabilities' => $package['capabilities'] ?? [],
                // Include both monthly and yearly features
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
            return ['amount' => 0, 'percentage' => 0, 'formatted' => '$0'];
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

    public function comparisonFeatures(): array
    {
        return collect(config('billing.comparison_features'))->map(function ($feature, $key) {
            return [
                'key' => $key,
                'name' => $feature['name'],
                'description' => $feature['description'],
                'capability_key' => $feature['capability_key'],
            ];
        })->toArray();
    }
}
