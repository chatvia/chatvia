<?php

namespace Domain\Team\Actions\Roles;

use App\Team\Models\Permission;
use App\Team\Models\Role;
use Domain\Agent\Models\Agent;
use Domain\Team\Data\SaveTeamRoleData;
use Domain\Team\Exceptions\InviteRejectedException;
use Illuminate\Support\Facades\DB;

class UpdateTeamRoleAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(Role $role, SaveTeamRoleData $data): Role
    {
        $agentIds = $data->allAgents
            ? []
            : Agent::whereIn('external_id', $data->agentExternalIds)->pluck('id')->toArray();

        if (! $data->allAgents && count($agentIds) !== count($data->agentExternalIds)) {
            throw new InviteRejectedException('agent_ids', 'One or more selected agents are invalid.');
        }

        return DB::transaction(function () use ($role, $data, $agentIds): Role {
            $role->update(['name' => $data->name]);

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
