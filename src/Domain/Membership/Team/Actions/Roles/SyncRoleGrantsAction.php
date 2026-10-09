<?php

namespace Domain\Membership\Team\Actions\Roles;

use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Support\Exceptions\DomainValidationException;

/**
 * Applies what a role grants, as chosen in the role editor: its permission
 * codes and the agents it may reach. Shared by {@see CreateTeamRoleAction} and
 * {@see UpdateTeamRoleAction} so both save a role the same way.
 *
 * The all-agents meta-permission ({@see Permission::ACCESS_ALL_AGENTS}) belongs
 * to the "all agents" switch, never to the permission list: an incoming copy is
 * dropped and it is added back only when the switch is on. Otherwise turning
 * the switch off would leave the stale permission behind and the role would
 * still see every agent. When the switch is on the explicit agent list is
 * cleared; when it is off every chosen agent must belong to the team.
 *
 * Callers run it inside their transaction, so a rejected agent list rolls back
 * the whole save.
 */
class SyncRoleGrantsAction
{
    public function __construct(
        private readonly SyncRolePermissionsAction $syncPermissions,
    ) {}

    /**
     * @throws DomainValidationException when a chosen agent is not in the team
     */
    public function execute(Role $role, SaveTeamRoleData $data): void
    {
        $agentIds = $data->allAgents ? [] : $this->teamAgentIds($data);

        $this->syncPermissions->execute($role, $this->permissionCodes($data));

        if ($data->allAgents) {
            $role->agents()->detach();

            return;
        }

        $role->agents()->sync($agentIds);
    }

    /**
     * @return list<string>
     */
    private function permissionCodes(SaveTeamRoleData $data): array
    {
        return collect($data->permissions)
            ->reject(fn (string $permission): bool => $permission === Permission::ACCESS_ALL_AGENTS)
            ->when($data->allAgents, fn ($permissions) => $permissions->push(Permission::ACCESS_ALL_AGENTS))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     *
     * @throws DomainValidationException
     */
    private function teamAgentIds(SaveTeamRoleData $data): array
    {
        $agentIds = Agent::query()
            ->where('team_id', $data->team->id)
            ->whereIn('external_id', $data->agentExternalIds)
            ->pluck('id')
            ->all();

        if (count($agentIds) !== count($data->agentExternalIds)) {
            throw new DomainValidationException('agent_ids', __('One or more selected agents are invalid.'));
        }

        return $agentIds;
    }
}
