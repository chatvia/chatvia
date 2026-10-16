<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Team;

use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamPermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A team role's name, agent access and permissions. Only the workspace owner
 * may change a team's roles. An agent id that is not one of the team's agents
 * fails validation.
 */
class SaveApiTeamRoleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
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

    public function toData(Team $team): SaveTeamRoleData
    {
        return new SaveTeamRoleData(
            team: $team,
            name: $this->string('name')->toString(),
            permissions: array_values((array) $this->validated('permissions', [])),
            allAgents: $this->boolean('all_agents'),
            agentExternalIds: array_values((array) $this->validated('agent_ids', [])),
        );
    }
}
