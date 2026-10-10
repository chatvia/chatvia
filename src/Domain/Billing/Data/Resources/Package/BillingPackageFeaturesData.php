<?php

namespace Domain\Billing\Data\Resources\Package;

use Domain\Billing\Services\Catalog\PlanFeatureList;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The feature bullets of a plan card, per billing cycle (only the message
 * volume differs between the two). Built from the plan by
 * {@see PlanFeatureList}.
 */
#[TypeScript]
class BillingPackageFeaturesData extends Data
{
    public function __construct(
        /** @var Collection<int, PlanFeatureData> */
        public Collection $monthly,
        /** @var Collection<int, PlanFeatureData> */
        public Collection $yearly,
    ) {}

    /** No bullets, for a page that has no plan to show. */
    public static function none(): self
    {
        return new self(monthly: collect(), yearly: collect());
    }
}
