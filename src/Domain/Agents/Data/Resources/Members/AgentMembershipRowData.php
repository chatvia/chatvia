<?php

namespace Domain\Agents\Data\Resources\Members;

use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Minimal agent reference used by the attach/detach sheets (connectors,
 * functions, …) — just enough to render a chip in the picker.
 */
#[TypeScript]
class AgentMembershipRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->external_id,
            name: $agent->name,
        );
    }
}
