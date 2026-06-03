<?php

namespace Domain\Membership\Team\Data\Resources\Team;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamAgentData extends Data
{
    public function __construct(
        public int $id,
        public string $external_id,
        public string $name,
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->id,
            external_id: $agent->external_id,
            name: $agent->name,
        );
    }
}
