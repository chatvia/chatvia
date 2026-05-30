<?php

namespace Domain\Agents\Data;

use Spatie\LaravelData\Data;

/**
 * Inputs for flipping a single LLM on or off for an agent.
 */
class ToggleAgentLlmData extends Data
{
    public function __construct(
        public string $model_id,
        public bool $status,
    ) {}
}
