<?php

namespace Domain\Integration\Function\Data\Resources;

use Domain\Integration\Function\Models\AgentFunction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row on the agent's Functions page — the function shape plus the per-agent
 * attachment flag.
 */
#[TypeScript]
class AgentFunctionRowData extends Data
{
    public function __construct(
        public string $id,
        public string $internal_name,
        public ?string $display_name,
        public ?string $description,
        public bool $is_enabled,
        public bool $is_attached,
    ) {}

    public static function fromModel(AgentFunction $function, bool $isAttached): self
    {
        $base = AgentFunctionData::fromModel($function)->toArray();

        return new self(
            id: (string) ($base['id'] ?? $function->external_id),
            internal_name: (string) ($base['internal_name'] ?? $function->internal_name),
            display_name: $base['display_name'] ?? $function->display_name,
            description: $base['description'] ?? $function->description,
            is_enabled: (bool) ($base['is_enabled'] ?? $function->is_enabled),
            is_attached: $isAttached,
        );
    }
}
