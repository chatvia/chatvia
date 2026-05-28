<?php

namespace Domain\Teams\Actions;

use App\Teams\Models\Permission;
use App\Teams\Models\Role;
use Domain\Agents\Models\Agent;
use Domain\Teams\Data\SaveTeamRoleData;
use Domain\Teams\Exceptions\InviteRejectedException;
use Illuminate\Support\Facades\DB;

class CreateTeamRoleAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(SaveTeamRoleData $data): Role
    {
        $team = $data->team;
        $code = strtolower(str_replace(' ', '_', $data->name));

        if ($team->roles()->where('code', $code)->exists()) {
            throw new InviteRejectedException('name', 'A role with this name already exists.');
        }

        $agentIds = $data->allAgents
            ? []
            : Agent::whereIn('external_id', $data->agentExternalIds)->pluck('id')->toArray();

        if (! $data->allAgents && count($agentIds) !== count($data->agentExternalIds)) {
            throw new InviteRejectedException('agent_ids', 'One or more selected agents are invalid.');
        }

        return DB::transaction(function () use ($data, $code, $agentIds): Role {
            /** @var Role $role */
            $role = Role::query()->create([
                'name' => $data->name,
                'code' => $code,
                'team_id' => $data->team->id,
                'guard_name' => 'web',
            ]);

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
            }

            if (! $data->allAgents) {
                $role->agents()->sync($agentIds);
            }

            return $role;
        });
    }
}
