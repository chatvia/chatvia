<?php

namespace Domain\Membership\Team\Data\Resources\Roles;

use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A team role as the team page and role editor show it: its permission codes,
 * whether it reaches every agent, and the agents it reaches otherwise.
 */
#[TypeScript]
class TeamRoleData extends Data
{
    public function __construct(
        public int $id,
        public string $external_id,
        public string $code,
        public string $name,
        public ?string $description,
        /** @var string[] */
        public array $permissions,
        public bool $all_agents,
        /** @var string[] */
        public array $agent_ids,
    ) {}

    public static function fromModel(Role $role): self
    {
        return new self(
            id: $role->id,
            external_id: $role->external_id,
            code: $role->code,
            name: $role->name,
            description: $role->description,
            // The all-agents meta-permission is surfaced through `all_agents`,
            // not as a member of the permissions list — exposing it in both
            // lets the edit form round-trip it back and silently re-grant
            // all-agent access even after the user unchecks "All Agents".
            permissions: $role->permissions
                ->pluck('name')
                ->reject(fn (string $name): bool => $name === Permission::ACCESS_ALL_AGENTS)
                ->values()
                ->toArray(),
            all_agents: $role->hasPermissionTo(Permission::ACCESS_ALL_AGENTS),
            agent_ids: $role->agents()->pluck('agents.external_id')->toArray(),
        );
    }

    /**
     * @return Collection<int, self>
     */
    public static function collectForTeam(Team $team): Collection
    {
        return $team->roles()
            ->with('permissions')
            ->get()
            ->map(fn (Role $role): self => self::fromModel($role));
    }
}
