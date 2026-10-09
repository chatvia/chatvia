<?php

namespace Domain\Billing\Data\Resources\Usage;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One usage figure: the limit, what is used, and what is left.
 */
#[TypeScript]
class UsageStatsData extends Data
{
    public function __construct(
        public int $limit,
        public int $used,
        public int $remaining,
    ) {}
}
