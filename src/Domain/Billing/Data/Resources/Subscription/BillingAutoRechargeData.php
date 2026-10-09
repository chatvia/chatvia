<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Resources\Subscription;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The auto-recharge settings as shown on the billing page.
 */
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
