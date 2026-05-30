<?php

namespace Domain\Agents\Data\Resources;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Shared props the Settings → Templates editor renders alongside the template
 * itself (create + edit pages): LLM catalog, the user's library prompts for
 * the persona picker, language options, and the tuning defaults.
 */
#[TypeScript]
class AgentTemplateEditorPropsData extends Data
{
    public function __construct(
        /** @var mixed Llm catalog projected by User::formattedLlms() */
        public mixed $catalog,
        /** @var DataCollection<int, AgentPromptPickerData> */
        #[DataCollectionOf(AgentPromptPickerData::class)]
        public DataCollection $prompts,
        /** @var array<string, mixed> */
        public array $availableLanguages,
        public AgentLlmDefaultSettingsData $defaultLlmSettings,
        /** @var array<string, mixed> */
        public array $appearanceDefaults,
        /** @var array<string, mixed> */
        public array $widgetDefaults,
    ) {}
}
