<?php

namespace App\Data\Resources\Team;

use App\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamAgentData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->id,
            name: $agent->name,
        );
    }
}
