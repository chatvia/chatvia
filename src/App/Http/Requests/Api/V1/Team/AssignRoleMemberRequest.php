<?php

namespace App\Http\Requests\Api\V1\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Public-API body for putting a user on a team role. Same gate as the web
 * side (TeamPolicy::updateTeamMember). `user_id` is the user's public id and
 * must name a user of the caller's own workspace.
 */
class AssignRoleMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && (bool) $this->user()?->can('updateTeamMember', $team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string'],
        ];
    }

    /**
     * The teammate the body names; 404 when it is not a teammate of the
     * caller's own workspace, so the endpoint never confirms another workspace's ids.
     */
    public function member(): User
    {
        /** @var User */
        return $this->user()->workspace()->teammates()
            ->where('users.external_id', $this->string('user_id')->toString())
            ->firstOrFail();
    }
}
