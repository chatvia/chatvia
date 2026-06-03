<?php

namespace Domain\Billing\Data\Resources\Subscription;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingAddonMessageCreditsData extends Data
{
    public function __construct(
        public int $manual_messages,
        public int $auto_recharge_messages,
    ) {}
}
