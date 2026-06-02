<?php

namespace Domain\Topic\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TopicSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $count,
    ) {}
}
