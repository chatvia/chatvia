<?php

namespace Domain\Agents\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FeedbackData extends Data
{
    public function __construct(
        public int $positive,
        public int $negative,
        public int $total,
    ) {}
}
