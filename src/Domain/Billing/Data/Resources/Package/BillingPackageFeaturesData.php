<?php

namespace Domain\Billing\Data\Resources\Package;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The marketing feature lists of a plan, per billing cycle.
 */
#[TypeScript]
class BillingPackageFeaturesData extends Data
{
    public function __construct(
        /** @var Collection<int, string> */
        public Collection $monthly,
        /** @var Collection<int, string> */
        public Collection $yearly,
    ) {}

    /**
     * @param  array{monthly?: list<string>, yearly?: list<string>}  $features
     */
    public static function fromArray(array $features): self
    {
        return new self(
            monthly: collect($features['monthly'] ?? []),
            yearly: collect($features['yearly'] ?? []),
        );
    }
}
