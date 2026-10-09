<?php

namespace Domain\Agent\Data\Settings;

use Spatie\LaravelData\Data;

/**
 * The model an agent answers with (by its public name) and the generation
 * settings to store with it.
 */
class UpdateAgentLlmData extends Data
{
    public function __construct(
        public string $model_external_name,
        public float $temperature,
        public int $top_k,
        public float $top_p,
        public float $presence_penalty,
        public float $frequency_penalty,
    ) {}
}
