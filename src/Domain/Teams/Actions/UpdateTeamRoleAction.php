<?php

namespace Domain\Teams\Actions;

use App\Teams\Models\Permission;
use App\Teams\Models\Role;
use Domain\Agents\Models\Agent;
use Domain\Teams\Data\SaveTeamRoleData;
use Domain\Teams\Exceptions\InviteRejectedException;
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

            // Chat-only teams carry no dashboard permissions — only agent access.
            $permissions = $data->team->can_access_dashboard ? $data->permissions : [];
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
