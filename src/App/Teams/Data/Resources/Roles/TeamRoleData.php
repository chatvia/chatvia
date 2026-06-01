<?php

namespace App\Teams\Data\Resources\Roles;

use App\Teams\Models\Role;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TeamRoleData extends Data
{
    public function __construct(
        public int $id,
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
            code: $role->code,
            name: $role->name,
            description: $role->description,
            permissions: $role->permissions->pluck('name')->toArray(),
            all_agents: $role->hasPermissionTo('agents.access-all'),
            agent_ids: $role->agents()->pluck('agents.external_id')->toArray(),
        );
    }
}
