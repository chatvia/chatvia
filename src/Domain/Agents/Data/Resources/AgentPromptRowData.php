<?php

namespace Domain\Agents\Data\Resources;

use Domain\Agents\Models\AgentPrompt;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row shape on the Settings → Prompts management page — extends the picker
 * fields with the per-prompt agent count and a formatted creation date.
 */
#[TypeScript]
class AgentPromptRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $prompt,
        public int $agents_count,
        public ?string $created_at,
    ) {}

    public static function fromModel(AgentPrompt $prompt): self
    {
        return new self(
            id: $prompt->external_id,
            name: $prompt->name,
            prompt: $prompt->prompt,
            agents_count: (int) ($prompt->agents_count ?? 0),
            created_at: $prompt->created_at?->toFormattedDateString(),
        );
    }
}
