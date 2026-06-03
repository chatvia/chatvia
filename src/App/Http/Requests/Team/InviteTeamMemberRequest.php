<?php

namespace App\Http\Requests\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\InviteTeamMemberData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');
        $user = $this->user();

        return $team instanceof Team
            && $user instanceof User
            && ($user->ownsTeam($team) || $user->hasTeamPermission($team, 'team.invite'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $validRoles = $team instanceof Team
            ? $team->roles()->where('code', '!=', 'owner')->pluck('code')->toArray()
            : [];

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:user_id'],
            'role' => ['required', 'string', Rule::in($validRoles)],
        ];
    }

    public function toData(Team $team): InviteTeamMemberData
    {
        /** @var User $user */
        $user = $this->user();

        return new InviteTeamMemberData(
            team: $team,
            inviter: $user,
            role: $this->string('role')->toString(),
            userId: $this->input('user_id') !== null ? (int) $this->input('user_id') : null,
            email: $this->input('email'),
        );
    }
}
