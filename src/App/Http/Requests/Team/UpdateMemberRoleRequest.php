<?php

namespace App\Http\Requests\Team;

use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
