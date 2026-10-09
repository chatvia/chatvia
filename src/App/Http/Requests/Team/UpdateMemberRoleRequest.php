<?php

namespace App\Http\Requests\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\UpdateMemberRoleData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moves a team member to another role. Only the owner may (TeamPolicy::
 * updateTeamMember), and only to a role a member can hold — never the owner role.
 */
class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && $this->user()?->can('updateTeamMember', $team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $validRoles = $team instanceof Team
            ? $team->invitableRoles()->pluck('code')->toArray()
            : [];

        return [
            'role' => ['required', 'string', Rule::in($validRoles)],
        ];
    }

    public function toData(Team $team, User $member): UpdateMemberRoleData
    {
        return new UpdateMemberRoleData(
            team: $team,
            member: $member,
            roleCode: $this->string('role')->toString(),
        );
    }
}
