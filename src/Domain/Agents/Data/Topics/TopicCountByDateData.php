<?php

namespace Domain\Agents\Data\Topics;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TopicCountByDateData extends Data
{
    public function __construct(
        public string $date,
        public int $count,
    ) {}
}
