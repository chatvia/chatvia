<?php

namespace Domain\Agents\Data\Resources;

use Domain\Agents\Models\AgentTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row shape on the Settings → Templates management page.
 */
#[TypeScript]
class AgentTemplateRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public int $agents_count,
        public ?string $created_at,
    ) {}

    public static function fromModel(AgentTemplate $template): self
    {
        return new self(
            id: $template->external_id,
            name: $template->name,
            agents_count: (int) ($template->agents_count ?? 0),
            created_at: $template->created_at?->toFormattedDateString(),
        );
    }
}
