<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Catalog;

use Domain\Billing\Actions\Catalog\SaveBillingPlanAction;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Enums\PlanFeature;
use Spatie\LaravelData\Data;

/**
 * A plan as the admin panel saves it ({@see SaveBillingPlanAction}): its
 * texts, flags, prices in the smallest currency unit (cents), limits,
 * features and the extra feature lines shown as typed. The Stripe ids are not
 * part of it: only the Stripe sync writes those.
 *
 * A null or zero yearly price means the plan is not sold yearly.
 */
class BillingPlanData extends Data
{
    /**
     * @param  list<PlanFeature>  $highlights  From PlanFeature::highlights().
     * @param  list<string>  $extraFeatures
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $description,
        public ?string $icon,
        public int $sortOrder,
        public bool $isActive,
        public bool $isDefault,
        public bool $isPopular,
        public bool $contactSales,
        public int $monthlyPriceCents,
        public ?int $yearlyPriceCents,
        public string $currency,
        public BillingPlanLimitsData $limits,
        public BillingPackageCapabilitiesData $capabilities,
        public array $extraFeatures = [],
        public array $highlights = [],
    ) {}
}
