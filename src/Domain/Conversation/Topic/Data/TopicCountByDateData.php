<?php

namespace Domain\Conversation\Topic\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How many conversations touched a topic on one day.
 */
#[TypeScript]
class TopicCountByDateData extends Data
{
    public function __construct(
        public string $date,
        public int $count,
    ) {}
}
