<?php

namespace Domain\Llm\Data;

use Domain\Llm\Models\Llm;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A catalog model as the model picker shows it.
 */
#[TypeScript]
class LlmModelData extends Data
{
    /**
     * @param  array<string, bool>  $features  capability flags keyed by name (e.g. `vision`, `function_calling`)
     */
    public function __construct(
        public string $id, // gpt-4o-mini
        public string $internal_id, // openai/gpt-4o-mini
        public string $name, // GPT-4o Mini
        public ?string $description,
        public ?string $icon,
        public ?int $context_window,
        public array $features,
        public ?string $location,
        public bool $is_reasoning,
        public bool $is_selected = true,
    ) {}

    public static function fromLlmModel(Llm $llm): self
    {
        return new LlmModelData(
            id: $llm->external_name,
            internal_id: $llm->internal_name,
            name: $llm->descriptive_name,
            description: $llm->description,
            icon: $llm->icon,
            context_window: $llm->context_window,
            features: $llm->features,
            location: $llm->location,
            is_reasoning: $llm->is_reasoning,
        );
    }
}
