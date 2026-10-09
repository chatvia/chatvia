<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Resources\Subscription;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Unexpired extra messages, bought by hand and by auto-recharge.
 */
#[TypeScript]
class BillingAddonMessageCreditsData extends Data
{
    public function __construct(
        public int $manual_messages,
        public int $auto_recharge_messages,
    ) {}
}
