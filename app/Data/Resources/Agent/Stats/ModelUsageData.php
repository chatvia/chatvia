<?php

namespace App\Data\Resources\Agent\Stats;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ModelUsageData extends Data
{
    public function __construct(
        public string $name,
        public string $icon,
        public int $count,
        public float $percentage,
        public ModelFeedbackData $feedback,
    ) {}
}
