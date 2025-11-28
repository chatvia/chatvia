<?php

namespace App\Data\Chat;

use App\Models\Llm;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LlmModelData extends Data
{
    public function __construct(
        public string $id, // gpt-4o-mini
        public string $internal_id, // openai/gpt-4o-mini
        public string $name, // GPT-4o Mini
        public string $description,
        public string $icon,
        public int $context_window,
        public array $features,
        public string $location,
        public bool $reasoning,
        public bool $user_allowed_to_use = false,
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
            reasoning: $llm->reasoning,
        );
    }
}
