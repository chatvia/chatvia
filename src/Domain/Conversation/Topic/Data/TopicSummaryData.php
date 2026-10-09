<?php

declare(strict_types=1);

namespace Domain\Conversation\Topic\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A topic's conversation count over the whole chart range.
 */
#[TypeScript]
class TopicSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $count,
    ) {}
}
