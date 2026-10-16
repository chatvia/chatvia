<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Team;

use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamPermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A partial update of a team role: a field left out keeps its current value
 * (the store request, SaveApiTeamRoleRequest, requires the full shape). Only
 * the workspace owner may change a team's roles.
 */
class UpdateApiTeamRoleRequest extends FormRequest
{
    /**
     * The dashboard's gate (TeamPolicy::update). Another workspace's team never
     * gets here: `workspace.scope` answers 404 first.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && (bool) $this->user()?->can('update', $team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The role's name.
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // Grant access to every agent in the team.
            'all_agents' => ['sometimes', 'boolean'],
            // IDs of the agents this role can access (ignored when all_agents is true).
            'agent_ids' => ['sometimes', 'array'],
            'agent_ids.*' => ['string'],
            // Permission codes to grant the role's members.
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(app(TeamPermissionCatalog::class)->codes())],
        ];
    }

    /** The submitted fields merged over the role's current state. */
    public function toData(Team $team, Role $role): SaveTeamRoleData
    {
        $role->loadMissing(['permissions', 'agents']);

        $currentPermissions = $role->permissions->pluck('name')
            ->reject(fn (string $name): bool => $name === Permission::ACCESS_ALL_AGENTS)
            ->values()->all();

        return new SaveTeamRoleData(
            team: $team,
            name: $this->has('name') ? $this->string('name')->toString() : $role->name,
            permissions: $this->has('permissions')
                ? array_values((array) $this->validated('permissions', []))
                : $currentPermissions,
            allAgents: $this->has('all_agents')
                ? $this->boolean('all_agents')
                : $role->permissions->contains(fn (Permission $permission): bool => $permission->name === Permission::ACCESS_ALL_AGENTS),
            agentExternalIds: $this->has('agent_ids')
                ? array_values((array) $this->validated('agent_ids', []))
                : $role->agents->pluck('external_id')->all(),
        );
    }
}
