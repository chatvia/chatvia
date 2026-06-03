<?php

namespace Domain\Billing\Data\Resources\Quota;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingQuotaLimitsData extends Data
{
    public function __construct(
        public BillingQuotaPeriodData $monthly,
        public BillingQuotaPeriodData $yearly,
        public BillingQuotaFixedData $fixed,
    ) {}

    public static function fromArray(array $limits): self
    {
        return new self(
            monthly: BillingQuotaPeriodData::fromArray($limits['monthly'] ?? []),
            yearly: BillingQuotaPeriodData::fromArray($limits['yearly'] ?? []),
            fixed: BillingQuotaFixedData::fromArray($limits['fixed'] ?? []),
        );
    }
}
