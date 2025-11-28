<?php

namespace App\Data\Resources\Agent;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgentLlmDefaultSettingsData extends Data
{
    public function __construct(
        public float $temperature = 1.0,
        public int $top_k = 0,
        public float $top_p = 1.0,
        public float $presence_penalty = 0.0,
        public float $frequency_penalty = 0.0,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }
}
