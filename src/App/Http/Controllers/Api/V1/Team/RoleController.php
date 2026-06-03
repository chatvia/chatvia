<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Resources\Api\V1\Team\RoleData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Actions\Roles\UpdateTeamRoleAction;
use Domain\Membership\Team\Data\RolePayloadData;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Exceptions\InviteRejectedException;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Roles live inside a team. A role grants access to a set of agents — all of
 * them or a specific list — and carries the dashboard permissions its members
 * hold. After creating a role, assign users to it via
 * `POST /api/v1/teams/{team}/roles/{role}/users`.
 */
#[Group(name: 'Teams', weight: 40)]
class RoleController
{
    /**
     * Create role
     *
     * @return array{data: RoleData}
     */
    public function store(Request $request, Team $team, RolePayloadData $payload, CreateTeamRoleAction $action): array
    {
        abort_unless($this->accountOwner($request)->ownsTeam($team), 403);

        try {
            $role = $action->execute($this->toSaveData($team, $payload));
        } catch (InviteRejectedException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return ['data' => RoleData::fromModel($role)];
    }

    /**
     * Update role
     *
     * Replaces the role's name, agent access, and permissions with the values
     * supplied.
     *
     * @return array{data: RoleData}
     */
    public function update(Request $request, Team $team, Role $role, RolePayloadData $payload, UpdateTeamRoleAction $action): array
    {
        abort_unless($this->accountOwner($request)->ownsTeam($team), 403);
        abort_unless($role->team_id === $team->id, 404);

        try {
            $updated = $action->execute($role, $this->toSaveData($team, $payload));
        } catch (InviteRejectedException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return ['data' => RoleData::fromModel($updated)];
    }

    private function toSaveData(Team $team, RolePayloadData $payload): SaveTeamRoleData
    {
        return new SaveTeamRoleData(
            team: $team,
            name: $payload->name,
            permissions: $payload->permissions,
            allAgents: $payload->all_agents,
            agentExternalIds: $payload->agent_ids,
        );
    }

    private function accountOwner(Request $request): User
    {
        /** @var User $account */
        $account = $request->user();

        return $account->belongsToWorkspace() ? $account->workspace : $account;
    }
}
