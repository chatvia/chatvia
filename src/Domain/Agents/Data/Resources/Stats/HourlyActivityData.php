<?php

namespace Domain\Agents\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HourlyActivityData extends Data
{
    public function __construct(
        public int $hour,
        public int $count,
    ) {}
}
