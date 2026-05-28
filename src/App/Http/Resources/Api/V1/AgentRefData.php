<?php

namespace App\Http\Resources\Api\V1;

use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;
use Support\Enums\AgentType;

/**
 * A lightweight agent reference (id, name, type), nested under a team in the
 * teams tree.
 */
class AgentRefData extends Data
{
    public function __construct(
        /**
         * The agent's ID.
         */
        public string $id,
        /**
         * Display name of the agent.
         */
        public string $name,
        /**
         * Agent kind.
         */
        public AgentType $type,
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->external_id,
            name: $agent->name,
            type: AgentType::from($agent->type),
        );
    }
}
