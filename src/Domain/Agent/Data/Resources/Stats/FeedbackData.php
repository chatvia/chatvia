<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Thumbs up and down given on the agent's replies, all time.
 */
#[TypeScript]
class FeedbackData extends Data
{
    public function __construct(
        public int $positive,
        public int $negative,
        public int $total,
    ) {}
}
