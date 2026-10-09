<?php

namespace Domain\Agent\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One of the agent's most retrieved knowledge files and its document type.
 */
#[TypeScript]
class TopKnowledgeData extends Data
{
    public function __construct(
        public string $name,
        public ?string $type,
        public int $accessed,
    ) {}
}
