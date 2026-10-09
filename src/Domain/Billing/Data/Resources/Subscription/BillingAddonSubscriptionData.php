<?php

namespace Domain\Billing\Data\Resources\Subscription;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The state of one add-on subscription on the billing page.
 */
#[TypeScript]
class BillingAddonSubscriptionData extends Data
{
    public function __construct(
        public bool $active,
        public int $quantity,
        public ?string $cancels_at,
    ) {}
}
