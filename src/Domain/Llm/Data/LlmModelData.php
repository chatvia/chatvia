<?php

namespace Domain\Llm\Data;

use Domain\Llm\Models\Llm;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A catalog model as the model picker shows it: its id, its name at its
 * provider and the provider's name, and whether it reasons.
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
    ) {}

    public static function fromLlmModel(Llm $llm, bool $selected = true): self
    {
        return new LlmModelData(
            id: $llm->id,
            name: $llm->name,
            provider: $llm->provider->name,
            is_reasoning: $llm->reasons(),
            is_selected: $selected,
        );
    }
}
