<?php

namespace App\Data\Resources\Agent;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgentLlmSettingsData extends Data
{
    public function __construct(
        public float $temperature,
        public int $top_k,
        public float $top_p,
        public float $presence_penalty,
        public float $frequency_penalty,
    ) {}

    public static function fromArray(array $settings): self
    {
        return new self(
            temperature: (float) ($settings['temperature'] ?? 1.0),
            top_k: (int) ($settings['top_k'] ?? 0),
            top_p: (float) ($settings['top_p'] ?? 1.0),
            presence_penalty: (float) ($settings['presence_penalty'] ?? 0.0),
            frequency_penalty: (float) ($settings['frequency_penalty'] ?? 0.0),
        );
    }
}
