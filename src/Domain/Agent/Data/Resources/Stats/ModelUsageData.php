<?php

namespace Domain\Agent\Data\Resources\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ModelUsageData extends Data
{
    public function __construct(
        public string $name,
        // Nullable: a usage rollup may reference a model since removed from
        // `llms`, or an LLM that simply has no icon set.
        public ?string $icon,
        public int $count,
        public float $percentage,
        public ModelFeedbackData $feedback,
    ) {}
}
