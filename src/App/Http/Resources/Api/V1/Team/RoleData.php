<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Team;

use Carbon\CarbonInterface;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Spatie\LaravelData\Data;

/**
 * A team role. It grants access to a set of agents — every agent
 * (`all_agents = true`) or the specific `agent_ids` listed — and carries the
 * dashboard `permissions` its members hold.
 */
class RoleData extends Data
{
    public function __construct(
        /**
         * The role's ID.
         */
        public string $id,
        /**
         * The role's name.
         */
        public string $name,
        /**
         * Optional description of the role.
         */
        public ?string $description,
        /**
         * Whether the role grants access to every agent in the team.
         */
        public bool $all_agents,
        /**
         * IDs of the agents this role can access. Empty when `all_agents` is true.
         *
         * @var list<string>
         */
        public array $agent_ids,
        /**
         * Permission codes granted to the role's members.
         *
         * @var list<string>
         */
        public array $permissions,
        /**
         * When the role was created.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(Role $role): self
    {
        $permissions = $role->permissions
            ->pluck('name')
            ->reject(fn (string $name): bool => $name === Permission::ACCESS_ALL_AGENTS)
            ->values()
            ->all();

        return new self(
            id: $role->external_id,
            name: $role->name,
            description: $role->description,
            all_agents: $role->permissions->contains(fn (Permission $permission): bool => $permission->name === Permission::ACCESS_ALL_AGENTS),
            agent_ids: $role->agents->pluck('external_id')->all(),
            permissions: $permissions,
            created_at: $role->created_at,
        );
    }
}
