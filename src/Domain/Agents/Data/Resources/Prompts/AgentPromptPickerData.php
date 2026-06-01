<?php

namespace Domain\Agents\Data\Resources\Prompts;

use Domain\Agents\Models\AgentPrompt;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Minimal projection of a reusable prompt for the agent Persona editor's
 * "Saved prompts" picker.
 */
#[TypeScript]
class AgentPromptPickerData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $prompt,
    ) {}

    public static function fromModel(AgentPrompt $prompt): self
    {
        return new self(
            id: $prompt->external_id,
            name: $prompt->name,
            prompt: $prompt->prompt,
        );
    }
}
