<?php

namespace Domain\Membership\Team\Actions\Roles;

use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Support\Exceptions\DomainValidationException;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Illuminate\Support\Facades\DB;

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
            : Agent::whereIn('external_id', $data->agentExternalIds)->pluck('id')->toArray();

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

            $permissions = $data->permissions;
            if ($data->allAgents) {
                $permissions[] = 'agents.access-all';
            }

            if (! empty($permissions)) {
                $permissionModels = collect($permissions)->unique()->map(function ($permissionName) {
                    return Permission::firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);
                });

                $role->syncPermissions($permissionModels);
            }

            if (! $data->allAgents) {
                $role->agents()->sync($agentIds);
            }

            return $role;
        });
    }
}
