<?php

namespace App\Billing\Data\Resources\Subscription;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingAutoRechargeData extends Data
{
    public function __construct(
        public bool $enabled,
        public int $threshold,
        public int $amount,
        public ?string $last_triggered_at,
    ) {}
}
