<?php

namespace Domain\Agent\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Conversations started on one day of the selected range.
 */
#[TypeScript]
class ConversationTrendData extends Data
{
    public function __construct(
        public string $date,
        public int $conversations,
    ) {}
}
