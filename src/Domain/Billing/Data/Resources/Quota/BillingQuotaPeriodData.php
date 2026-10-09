<?php

namespace Domain\Billing\Data\Resources\Quota;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A plan's limits for one billing cycle (messages).
 */
#[TypeScript]
class BillingQuotaPeriodData extends Data
{
    public function __construct(
        public int $messages_limit = 0,
    ) {}

    /**
     * @param  array{messages_limit?: int}  $period
     */
    public static function fromArray(array $period): self
    {
        return new self(
            messages_limit: $period['messages_limit'] ?? 0,
        );
    }
}
