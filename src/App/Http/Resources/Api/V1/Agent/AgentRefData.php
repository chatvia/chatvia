<?php

namespace App\Http\Resources\Api\V1\Agent;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * A lightweight agent reference (id, name), nested under a team in the teams
 * tree.
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
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->external_id,
            name: $agent->name,
        );
    }
}
