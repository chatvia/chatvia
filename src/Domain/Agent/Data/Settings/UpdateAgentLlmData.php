<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Settings;

use Domain\Llm\Enums\ReasoningEffort;
use Spatie\LaravelData\Data;

/**
 * The model an agent answers with (its catalog id) and the generation
 * settings to store with it, including how hard it reasons.
 */
class UpdateAgentLlmData extends Data
{
    public function __construct(
        public int $model_id,
        public float $temperature,
        public int $top_k,
        public float $top_p,
        public float $presence_penalty,
        public float $frequency_penalty,
        public ReasoningEffort $reasoning_effort = ReasoningEffort::Off,
    ) {}
}
