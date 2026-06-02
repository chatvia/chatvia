<?php

namespace App\Billing\Data\Resources\Subscription;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingAddonSubscriptionData extends Data
{
    public function __construct(
        public bool $active,
        public int $quantity,
        public ?string $cancels_at,
    ) {}
}
