<?php

namespace Domain\Agents\Data\Topics;

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
