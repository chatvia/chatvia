<?php

namespace Domain\Agents\Data\Resources;

use Domain\Agents\Models\AgentTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Snake-case projection of a template for the Settings → Templates editor
 * form. The editor binds to one per-surface section at a time (Hosted Chat /
 * Widget / Bar); each `*_config` blob is the raw JSON the template stores for
 * that surface and is `null` when the template doesn't configure that surface.
 */
#[TypeScript]
class AgentTemplateEditorData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $prompt_id,
        public bool $is_public,
        /** @var array<string, mixed>|null */
        public ?array $hosted_chat_config,
        /** @var array<string, mixed>|null */
        public ?array $widget_config,
        /** @var array<string, mixed>|null */
        public ?array $bar_config,
    ) {}

    public static function fromModel(AgentTemplate $template): self
    {
        return new self(
            id: $template->external_id,
            name: $template->name,
            prompt_id: $template->agentPrompt?->external_id,
            is_public: (bool) $template->is_public,
            hosted_chat_config: $template->hosted_chat_config,
            widget_config: $template->widget_config,
            bar_config: $template->bar_config,
        );
    }
}
