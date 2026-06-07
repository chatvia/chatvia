<?php

namespace Domain\Membership\Team\Actions\Roles;

use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Illuminate\Support\Facades\DB;
use Support\Exceptions\DomainValidationException;

class CreateTeamRoleAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(SaveTeamRoleData $data): Role
    {
        $team = $data->team;
        $code = strtolower(str_replace(' ', '_', $data->name));

        if ($team->roles()->where('code', $code)->exists()) {
            throw new DomainValidationException('name', 'A role with this name already exists.');
        }

        $agentIds = $data->allAgents
            ? []
            : Agent::query()
                ->where('team_id', $team->id)
                ->whereIn('external_id', $data->agentExternalIds)
                ->pluck('id')
                ->toArray();

        if (! $data->allAgents && count($agentIds) !== count($data->agentExternalIds)) {
            throw new DomainValidationException('agent_ids', 'One or more selected agents are invalid.');
        }

        return DB::transaction(function () use ($data, $code, $agentIds): Role {
            /** @var Role $role */
            $role = Role::query()->create([
                'name' => $data->name,
                'code' => $code,
                'team_id' => $data->team->id,
                'guard_name' => 'web',
            ]);

            // `agents.access-all` is a meta-permission owned entirely by the
            // allAgents flag — strip any incoming copy, then add it back only
            // when the role grants all agents. Keeps create symmetric with
            // UpdateTeamRoleAction so the permission can never drift in.
            $permissions = collect($data->permissions)
                ->reject(fn (string $permission): bool => $permission === 'agents.access-all')
                ->when($data->allAgents, fn ($permissions) => $permissions->push('agents.access-all'))
                ->unique();

            if ($permissions->isNotEmpty()) {
                $permissionModels = $permissions->map(fn (string $permissionName): Permission => Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]));

                $role->syncPermissions($permissionModels);
            }

            if (! $data->allAgents) {
                $role->agents()->sync($agentIds);
            }

            return $role;
        });
    }
}
