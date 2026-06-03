<?php

namespace App\Http\Resources\Api\V1\Team;

use App\Http\Resources\Api\V1\Agent\AgentRefData;
use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Spatie\LaravelData\Data;

/**
 * A team with its agents and roles nested. Pass `id` as `team_id` when
 * creating agents, or in the team/role routes.
 */
class TeamData extends Data
{
    public function __construct(
        /**
         * The team's ID.
         */
        public string $id,
        /**
         * The team's name.
         */
        public string $name,
        /**
         * Agents that belong to this team.
         *
         * @var list<AgentRefData>
         */
        public array $agents,
        /**
         * Roles defined in this team.
         *
         * @var list<RoleData>
         */
        public array $roles,
        /**
         * When the team was created.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(Team $team): self
    {
        return new self(
            id: $team->external_id,
            name: $team->name,
            agents: $team->agents->map(fn (Agent $agent): AgentRefData => AgentRefData::fromModel($agent))->all(),
            roles: $team->roles->map(fn (Role $role): RoleData => RoleData::fromModel($role))->all(),
            created_at: $team->created_at,
        );
    }
}
