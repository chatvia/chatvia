<?php

namespace Domain\Membership\Team\Actions\Roles;

use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Illuminate\Support\Facades\DB;
use Support\Exceptions\DomainValidationException;

class UpdateTeamRoleAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Role $role, SaveTeamRoleData $data): Role
    {
        $agentIds = $data->allAgents
            ? []
            : Agent::query()
                ->where('team_id', $data->team->id)
                ->whereIn('external_id', $data->agentExternalIds)
                ->pluck('id')
                ->toArray();

        if (! $data->allAgents && count($agentIds) !== count($data->agentExternalIds)) {
            throw new DomainValidationException('agent_ids', 'One or more selected agents are invalid.');
        }

        return DB::transaction(function () use ($role, $data, $agentIds): Role {
            $role->update(['name' => $data->name]);

            // `agents.access-all` is a meta-permission owned entirely by the
            // allAgents flag — never a member of the general permissions list.
            // Strip any round-tripped copy first, then re-add it only when the
            // role actually grants all agents; otherwise unchecking "All Agents"
            // would leave the stale permission behind (the role still sees
            // everything) because syncPermissions only replaces what it's given.
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
            } else {
                $role->syncPermissions([]);
            }

            if ($data->allAgents) {
                $role->agents()->detach();
            } else {
                $role->agents()->sync($agentIds);
            }

            return $role->fresh();
        });
    }
}
