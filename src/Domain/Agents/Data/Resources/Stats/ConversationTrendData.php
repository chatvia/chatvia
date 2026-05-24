<?php

namespace Domain\Agents\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ConversationTrendData extends Data
{
    public function __construct(
        public string $date,
        public int $conversations,
    ) {}
}
