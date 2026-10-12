<?php

namespace App\Http\Requests\Team\Roles;

use App\Http\Rules\WorkspaceRule;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating or editing a team role from the role editor. Only the owner may
 * (TeamPolicy::update).
 */
class SaveTeamRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && $this->user()?->can('update', $team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'all_agents' => ['boolean'],
            'agent_ids' => ['exclude_if:all_agents,true', 'required', 'array', 'min:1'],
            'agent_ids.*' => ['string', WorkspaceRule::exists('agents', $this->user()->workspace(), 'external_id')],
        ];
    }

    public function toData(Team $team): SaveTeamRoleData
    {
        return new SaveTeamRoleData(
            team: $team,
            name: $this->string('name')->toString(),
            permissions: $this->input('permissions') ?? [],
            allAgents: $this->boolean('all_agents'),
            agentExternalIds: $this->input('agent_ids') ?? [],
        );
    }
}
