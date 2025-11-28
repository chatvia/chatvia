<?php

namespace App\Data\Resources\Agent;

use App\Data\Chat\LlmModelData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgentModelsResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, LlmModelData> */
        #[DataCollectionOf(LlmModelData::class)]
        public DataCollection $models,
        public AgentLlmSettingsData $settings,
        public AgentLlmDefaultSettingsData $default_settings,
    ) {}
}
