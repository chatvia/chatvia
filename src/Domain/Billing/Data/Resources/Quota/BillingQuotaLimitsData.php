<?php

namespace Domain\Billing\Data\Resources\Quota;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * All of a plan's limits: per month, per year and fixed.
 */
#[TypeScript]
class BillingQuotaLimitsData extends Data
{
    public function __construct(
        public BillingQuotaPeriodData $monthly,
        public BillingQuotaPeriodData $yearly,
        public BillingQuotaFixedData $fixed,
    ) {}

    /**
     * @param  array{monthly?: array<string, int>, yearly?: array<string, int>, fixed?: array<string, int>}  $limits
     */
    public static function fromArray(array $limits): self
    {
        return new self(
            monthly: BillingQuotaPeriodData::fromArray($limits['monthly'] ?? []),
            yearly: BillingQuotaPeriodData::fromArray($limits['yearly'] ?? []),
            fixed: BillingQuotaFixedData::fromArray($limits['fixed'] ?? []),
        );
    }
}
