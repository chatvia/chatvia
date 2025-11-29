<?php

namespace App\Data\Resources\Agent;

use App\Data\Chat\LlmModelData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgentModelsResourceData extends Data
{
    public function __construct(
        /** @var array<int, LlmModelData> */
        public array $models,
        public AgentLlmSettingsData $settings,
        public AgentLlmDefaultSettingsData $default_settings,
    ) {}
}
