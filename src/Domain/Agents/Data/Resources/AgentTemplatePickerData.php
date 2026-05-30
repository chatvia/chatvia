<?php

namespace Domain\Agents\Data\Resources;

use Domain\Agents\Models\AgentTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Minimal projection of an agent template used by the "attach a template"
 * picker on the agent setup banner.
 */
#[TypeScript]
class AgentTemplatePickerData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromModel(AgentTemplate $template): self
    {
        return new self(
            id: $template->external_id,
            name: $template->name,
        );
    }
}
