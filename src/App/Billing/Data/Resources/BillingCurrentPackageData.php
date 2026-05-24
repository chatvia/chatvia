<?php

namespace App\Billing\Data\Resources;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingCurrentPackageData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public BillingPackageFeaturesData $features,
        public int $monthly_price,
        public string $monthly_price_formatted,
        public int $yearly_price,
        public string $yearly_price_formatted,
        public bool $is_yearly,
        public bool $is_popular,
        public BillingQuotaLimitsData $limits,
        public BillingPackageCapabilitiesData $capabilities,
    ) {}

    public static function fromArray(array $package): self
    {
        $planId = $package['id'] ?? strtolower($package['name']);

        return new self(
            id: $planId,
            name: $package['name'],
            description: $package['description'] ?? '',
            features: BillingPackageFeaturesData::fromArray($package['features'] ?? []),
            monthly_price: $package['monthly_price'],
            monthly_price_formatted: $package['monthly_price_formatted'],
            yearly_price: $package['yearly_price'],
            yearly_price_formatted: $package['yearly_price_formatted'],
            is_yearly: $package['is_yearly'] ?? false,
            is_popular: $package['is_popular'] ?? false,
            limits: BillingQuotaLimitsData::fromArray($package['quota_limits'] ?? []),
            capabilities: BillingPackageCapabilitiesData::forPlan($planId),
        );
    }
}
