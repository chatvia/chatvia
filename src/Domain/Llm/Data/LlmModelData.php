<?php

namespace Domain\Llm\Data;

use Domain\Llm\Models\Llm;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A catalog model as the model picker shows it: its id, its name at its
 * provider, the name people know it by and its maker's logo (models.dev,
 * as the hosted chat's picker shows them; null when models.dev does not
 * list it), the provider's name and logo (the picker groups models by
 * provider), whether it reasons, and whether it calls tools.
 */
#[TypeScript]
class LlmModelData extends Data
{
    public function __construct(
        public int $id,
        public string $name, // gpt-6-luna
        public string $provider, // OpenAI
        public bool $is_reasoning,
        public bool $is_selected = true,
        public ?string $provider_icon = null,
        public bool $calls_tools = true,
        public ?string $display_name = null, // GPT-6 Luna
        public ?string $logo = null,
    ) {}

    public static function fromLlmModel(Llm $llm, bool $selected = true, ?string $providerIcon = null, ?string $displayName = null, ?string $logo = null): self
    {
        return new LlmModelData(
            id: $llm->id,
            name: $llm->name,
            provider: $llm->provider->name,
            is_reasoning: $llm->reasons(),
            is_selected: $selected,
            provider_icon: $providerIcon,
            calls_tools: $llm->callsTools(),
            display_name: $displayName,
            logo: $logo,
        );
    }
}
