<?php

namespace Domain\Agent\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Conversations started in one hour of the day (UTC), all time.
 */
#[TypeScript]
class HourlyActivityData extends Data
{
    public function __construct(
        public int $hour,
        public int $count,
    ) {}
}
